<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Payments\PayoutGateway::class, fn () =>
            config('services.payments.driver') === 'paystack'
                ? new \App\Payments\PaystackGateway()
                : new \App\Payments\FakeGateway()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
