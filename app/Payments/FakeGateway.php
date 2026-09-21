<?php 

namespace App\Payments;

use Illuminate\Support\Str;

class FakeGateway implements PayoutGateway
{
    public function createRecipient(string $name, string $accountNumber, string $bankCode): string
    {
        return 'RCP_fake_' . Str::random(8);
    }

    public function transfer(string $recipientCode, int $amountKobo, string $reference, string $reason): array
    {
        return ['status' => 'success', 'provider_ref' => 'TRF_fake_' . Str::random(8)];
    }
}