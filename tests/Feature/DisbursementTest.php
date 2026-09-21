<?php

use Tests\TestCase;
use App\Models\VendorRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DisbursementTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_paid_at_booking_and_balance_on_confirm(): void
    {
        config(['services.payments.driver' => 'fake', 'services.internal.token' => 't']);
        VendorRecipient::create(['vendor_id' => 'v1', 'recipient_code' => 'RCP_x']);

        $h = ['Authorization' => 'Bearer t'];

        $this->postJson('/api/v1/budget/v1/disburse', ['booking_id' => 'b1', 'total_kobo' => 1_000_000], $h)
            ->assertCreated()
            ->assertJsonPath('deposit.status', 'success')
            ->assertJsonPath('balance.status', 'scheduled');

        $this->postJson('/api/v1/bookings/b1/confirm-event', [], $h)
            ->assertJsonPath('status', 'success');

        // replaying the booking must not double-pay
        $this->postJson('/api/v1/budget/v1/disburse', ['booking_id' => 'b1', 'total_kobo' => 1_000_000], $h)->assertCreated();
        $this->assertDatabaseCount('disbursements', 2);
    }
}