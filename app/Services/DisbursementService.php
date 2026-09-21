<?php

namespace App\Services;

use App\Enums\{DisbursementStatus, TrancheType};
use App\Models\{Disbursement, VendorRecipient};
use App\Payments\PayoutGateway;
use Illuminate\Support\Str;

class DisbursementService
{
    public function __construct(private PayoutGateway $gateway) {}

    /** Called at booking: record both tranches, pay the deposit now. */
    public function book(string $vendorId, string $bookingId, int $totalKobo, int $depositPct = 50): array
    {
        $deposit = intdiv($totalKobo * $depositPct, 100);

        $dep = $this->row($vendorId, $bookingId, TrancheType::DEPOSIT, $deposit, DisbursementStatus::PENDING);
        $bal = $this->row($vendorId, $bookingId, TrancheType::BALANCE, $totalKobo - $deposit, DisbursementStatus::SCHEDULED);

        $this->pay($dep);

        return [$dep->fresh(), $bal->fresh()];
    }

    /** Called on event-day confirmation: release the balance. */
    public function confirmEvent(string $bookingId): Disbursement
    {
        // Enums cast automatically if configured in the model, but manual queries use string values
        $bal = Disbursement::where([
            'booking_id' => $bookingId, 
            'tranche' => TrancheType::BALANCE->value
        ])->firstOrFail();

        if ($bal->status === DisbursementStatus::SCHEDULED) {
            $this->pay($bal);
        }
        return $bal->fresh();
    }

    private function row(string $vendorId, string $bookingId, TrancheType $tranche, int $amount, DisbursementStatus $status): Disbursement
    {
        return Disbursement::firstOrCreate(
            ['booking_id' => $bookingId, 'tranche' => $tranche->value],
            [
                'vendor_id' => $vendorId,
                'amount_kobo' => $amount,
                'status' => $status->value,
                'reference' => 'dsb_' . Str::uuid(),   // lowercase, unique
            ]
        );
    }

    private function pay(Disbursement $d): void
    {
        if (in_array($d->status, [DisbursementStatus::PROCESSING, DisbursementStatus::SUCCESS], true)) {
            return; // idempotent
        }

        $recipient = VendorRecipient::where('vendor_id', $d->vendor_id)->firstOrFail();

        try {
            $r = $this->gateway->transfer(
                $recipient->recipient_code, 
                $d->amount_kobo, 
                $d->reference,
                "{$d->tranche->value} for booking {$d->booking_id}"
            );

            $d->update([
                'status' => match ($r['status']) { 
                    'success' => DisbursementStatus::SUCCESS->value, 
                    'failed' => DisbursementStatus::FAILED->value, 
                    default => DisbursementStatus::PROCESSING->value 
                },
                'provider_ref' => $r['provider_ref'],
            ]);
        } catch (\Throwable $e) {
            $d->update([
                'status' => DisbursementStatus::FAILED->value, 
                'meta' => ['error' => $e->getMessage()]
            ]);
        }
    }
}
