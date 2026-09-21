<?php


namespace App\Payments;

interface PayoutGateway
{
    public function createRecipient(string $name, string $accountNumber, string $bankCode): string;

    /** @return array{status: string, provider_ref: string} */
    public function transfer(string $recipientCode, int $amountKobo, string $reference, string $reason): array;
}