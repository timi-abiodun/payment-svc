<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VendorRecipient;

class VendorRecipientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a vendor recipient
        $code = config('services.payments.driver') === 'paystack'
        ? app(\App\Payments\PayoutGateway::class)->createRecipient('Test Vendor', '0123456789', '058')
        : 'RCP_fake_test1234';

        VendorRecipient::updateOrCreate(
            ['vendor_id' => 'v1'], // Unique constraint check
            ['recipient_code' => $code]
        );
    }
}
