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
        $bal = Disbursement::where('booking_id', $bookingId)
            ->where('tranche', TrancheType::BALANCE->value)
            ->firstOrFail();

        $this->pay($bal);   // the claim inside pay() decides whether anything happens

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
        // Look up the recipient BEFORE claiming, so a missing vendor can't leave a row stuck in "processing"
        $recipient = VendorRecipient::where('vendor_id', $d->vendor_id)->firstOrFail();

        // Atomic claim: only one caller can move the row out of these states
        $claimed = Disbursement::whereKey($d->id)
            ->whereIn('status', [
                DisbursementStatus::SCHEDULED->value,
                DisbursementStatus::PENDING->value,
                DisbursementStatus::FAILED->value,     // allows retry after a failure
            ])
            ->update(['status' => DisbursementStatus::PROCESSING->value]);

        if ($claimed !== 1) {
            return; // already processing, paid, or claimed by a concurrent request
        }

        try {
            $r = $this->gateway->transfer(
                $recipient->recipient_code, $d->amount_kobo, $d->reference,
                "{$d->tranche->value} for booking {$d->booking_id}"
            );

            $this->settle($d, match ($r['status']) {
                'success' => DisbursementStatus::SUCCESS,
                'failed' => DisbursementStatus::FAILED,
                default => DisbursementStatus::PROCESSING,
            }, providerRef: $r['provider_ref']);
        } catch (\Throwable $e) {
            $this->settle($d, DisbursementStatus::FAILED, meta: ['error' => $e->getMessage()]);
        }
    }

    private function settle(
        Disbursement $d,
        DisbursementStatus $status,
        ?string $providerRef = null,
        ?array $meta = null
    ): void {
        $values = array_filter([
            'provider_ref' => $providerRef,
            'meta' => $meta ? json_encode($meta) : null,
        ]);

        if ($values) {
            Disbursement::whereKey($d->id)->update($values);
        }

        // Move status only if we still own the claim. If the webhook already
        // finalised the row, we don't overwrite it with an older state.
        Disbursement::whereKey($d->id)
            ->where('status', DisbursementStatus::PROCESSING->value)
            ->update(['status' => $status->value]);
    }
}
