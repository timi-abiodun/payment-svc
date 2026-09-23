<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Enums\DisbursementStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_id')->index();
            $table->string('booking_id');
            $table->string('tranche');                       // deposit | balance
            $table->unsignedBigInteger('amount_kobo');
            $table->string('status')->default(DisbursementStatus::SCHEDULED->value);    // scheduled|pending|processing|success|failed
            $table->string('reference')->unique(); // a unique reference for this disbursement, used for idempotency
            $table->string('provider_ref')->nullable()->unique();  // the gateway's reference for this payout if any
            $table->json('meta')->nullable(); // any extra info from the gateway, e.g. failure reason
            $table->timestamps();
            $table->unique(['booking_id', 'tranche']);       // idempotency guard: prevents duplicate tranches per booking
            $table->index(['vendor_id', 'created_at']);   // dashboard listing
            $table->index(['vendor_id', 'status']);       // paid / outstanding sums

            $table->foreign('vendor_id')->references('vendor_id')->on('vendor_recipients'); // ensures that a disbursement can only be created for a vendor that has a recipient set up
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
