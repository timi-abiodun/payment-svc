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

    public function show(string $vendorId)
    {
        $rows = Disbursement::where('vendor_id', $vendorId)->latest()->get();

        return [
            'vendor_id' => $vendorId,
            'paid_kobo' => $rows->where('status', DisbursementStatus::SUCCESS)->sum('amount_kobo'),
            'outstanding_kobo' => $rows->whereIn('status', [
                DisbursementStatus::SCHEDULED, 
                DisbursementStatus::PENDING, 
                DisbursementStatus::PROCESSING
            ])->sum('amount_kobo'),
            'disbursements' => $rows,
        ];
    }
}
