<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     *
     * Each module has its own limit calibrated to its expected usage pattern:
     *
     * - admin: authenticated human users interacting with the back-office UI.
     *          Keyed by user ID when authenticated, IP address otherwise.
     *
     * - subscription: public form submissions. Keyed by IP to slow down bots
     *                 and bulk submission attempts.
     *
     * - certified: public certificate lookups. More permissive than subscription
     *              because a single user may query multiple certificates.
     *              Keyed by IP.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('subscription', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->ip());
        });

        RateLimiter::for('certified', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->ip());
        });
    }
}
