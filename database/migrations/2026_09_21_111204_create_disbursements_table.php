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
            $table->string('reference')->unique();
            $table->string('provider_ref')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'tranche']);       // idempotency guard: prevents duplicate tranches per booking
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
