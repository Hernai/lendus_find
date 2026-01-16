<?php

namespace App\Providers;

use App\Contracts\ApiLoggerInterface;
use App\Contracts\DocumentStorageInterface;
use App\Contracts\SmsServiceInterface;
use App\Services\ApiLoggerService;
use App\Services\DocumentService;
use App\Services\ExternalApi\TwilioService;
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
        // Bind interfaces to implementations
        $this->app->bind(ApiLoggerInterface::class, ApiLoggerService::class);
        $this->app->bind(DocumentStorageInterface::class, DocumentService::class);

        // SmsServiceInterface binding requires tenant context
        // TwilioService is instantiated with tenant ID, so we use a factory
        $this->app->bind(SmsServiceInterface::class, function ($app) {
            $tenantId = $app->bound('tenant.id') ? $app->make('tenant.id') : null;
            return new TwilioService($tenantId);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for security-sensitive endpoints.
     */
    protected function configureRateLimiting(): void
    {
        // OTP request: 3 per minute per IP (prevents SMS bombing)
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // OTP verification: 5 attempts per minute per phone/email
        RateLimiter::for('otp-verify', function (Request $request) {
            $key = $request->input('phone') ?: $request->input('email') ?: $request->ip();
            return Limit::perMinute(5)->by($key);
        });

        // PIN login: 5 attempts per minute per phone
        RateLimiter::for('pin-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('phone', $request->ip()));
        });

        // Password login: 5 attempts per minute per email/IP
        RateLimiter::for('password-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email', $request->ip()));
        });

        // General API: 60 requests per minute per user/IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
