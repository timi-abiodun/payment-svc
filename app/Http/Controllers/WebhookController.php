<?php

namespace App\Http\Controllers;

use App\Enums\DisbursementStatus;
use App\Models\Disbursement;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function paystack(Request $r)
    {
        $expected = hash_hmac('sha512', $r->getContent(), config('services.paystack.secret'));
        abort_unless(hash_equals($expected, (string) $r->header('x-paystack-signature')), 401);

        $d = Disbursement::where('reference', $r->input('data.reference'))->first();

        // Use isFinal() to block redundant updates on terminal states
        if ($d && !$d->status->isFinal()) {
            match ($r->input('event')) {
                'transfer.success'   => $d->update(['status' => DisbursementStatus::SUCCESS]),
                'transfer.failed', 
                'transfer.reversed'  => $d->update(['status' => DisbursementStatus::FAILED]),
                default              => null,
            };
        }

        return response()->noContent();
    }
}