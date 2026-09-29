<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use App\Models\Customer;
use App\Observers\CustomerObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Customer::observe(CustomerObserver::class);

        RateLimiter::for('registro', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Mail::extend('brevo', function (array $config) {
            return new BrevoApiTransport($config['key']);
        });
    }
}
