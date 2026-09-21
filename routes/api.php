<?php

use App\Http\Controllers\WebhookController;
use App\Http\Controllers\DisbursementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public / Unprotected V1 Routes
    Route::get('/health', fn () => ['ok' => true, 'driver' => config('services.payments.driver')]);
    
    Route::post('/webhooks/paystack', [WebhookController::class, 'paystack']); 

    // Protected V1 Routes
    Route::middleware('service.token')->group(function () {
        Route::post('/budget/{vendorId}/disburse', [DisbursementController::class, 'store']);
        Route::post('/bookings/{bookingId}/confirm-event', [DisbursementController::class, 'confirm']);
        Route::get('/budget/{vendorId}', [DisbursementController::class, 'show']);
    });

});
