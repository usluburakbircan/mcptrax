<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Ücretsiz checker senkron dış istek attığı için hem dakikalık
        // (worker'ları kilitlemesin) hem günlük (lead magnet kotası) sınırlı.
        RateLimiter::for('checker', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(10)->by($request->ip()),
        ]);
    }
}
