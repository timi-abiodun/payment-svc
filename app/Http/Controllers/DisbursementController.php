<?php

namespace App\Http\Controllers;

use App\Models\Disbursement;
use App\Enums\DisbursementStatus;
use App\Services\DisbursementService;
use Illuminate\Http\Request;

class DisbursementController extends Controller
{
    public function __construct(private DisbursementService $svc) {}

    public function store(Request $r, string $vendorId)
    {
        $data = $r->validate([
            'booking_id' => 'required|string',
            'total_kobo' => 'required|integer|min:1',
            'deposit_percent' => 'integer|between:1,99',
        ]);

        [$dep, $bal] = $this->svc->book(
            $vendorId, $data['booking_id'], $data['total_kobo'], $data['deposit_percent'] ?? 50
        );

        return response()->json(['deposit' => $dep, 'balance' => $bal], 201);
    }

    public function confirm(string $bookingId)
    {
        return $this->svc->confirmEvent($bookingId);
    }

    public function show(Request $request, string $vendorId)
    {
        // One query for both totals, computed in the database
        $totals = Disbursement::where('vendor_id', $vendorId)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN amount_kobo END), 0) AS paid,
                COALESCE(SUM(CASE WHEN status IN (?, ?, ?) THEN amount_kobo END), 0) AS outstanding',
                [
                    DisbursementStatus::SUCCESS->value,
                    DisbursementStatus::SCHEDULED->value,
                    DisbursementStatus::PENDING->value,
                    DisbursementStatus::PROCESSING->value,
                ]
            )
            ->toBase()
            ->first();

        $disbursements = Disbursement::where('vendor_id', $vendorId)
            ->select(['id', 'booking_id', 'tranche', 'amount_kobo', 'status', 'provider_ref', 'created_at']) // no `meta`
            ->latest()
            ->latest('id')                                   // stable order when timestamps tie
            ->paginate(min((int) $request->query('per_page', 50), 100));

        return [
            'vendor_id' => $vendorId,
            'paid_kobo' => (int) $totals->paid,
            'outstanding_kobo' => (int) $totals->outstanding,
            'disbursements' => $disbursements,
        ];
    }
}
