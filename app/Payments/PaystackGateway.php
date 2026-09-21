<?php

namespace App\Payments;

use Illuminate\Support\Facades\Http;

class PaystackGateway implements PayoutGateway
{
    private function http()
    {
        return Http::withToken(config('services.paystack.secret'))
            ->baseUrl('https://api.paystack.co')
            ->acceptJson()
            ->timeout(15);
    }

    public function createRecipient(string $name, string $accountNumber, string $bankCode): string
    {
        $res = $this->http()->post('/transferrecipient', [
            'type' => 'nuban',
            'name' => $name,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => 'NGN',
        ])->throw()->json();

        return $res['data']['recipient_code'];
    }

    public function transfer(string $recipientCode, int $amountKobo, string $reference, string $reason): array
    {
        $res = $this->http()->post('/transfer', [
            'source' => 'balance',
            'amount' => $amountKobo,
            'recipient' => $recipientCode,
            'reference' => $reference,   // lowercase, unique: safe to retry
            'reason' => $reason,
        ])->throw()->json();

        return [
            'status' => $res['data']['status'],          // success | pending | otp | failed
            'provider_ref' => $res['data']['transfer_code'],
        ];
    }
}