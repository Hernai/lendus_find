<?php

namespace App\Providers;

use App\Contracts\ApiLoggerInterface;
use App\Contracts\DocumentStorageInterface;
use App\Contracts\KycServiceInterface;
use App\Contracts\SmsServiceInterface;
use App\Models\CachedPersonalAccessToken;
use App\Models\NotificationTemplate;
use App\Models\Product;
use App\Models\TenantApiConfig;
use App\Models\TenantBranding;
use App\Observers\V2ConfigCacheObserver;
use App\Services\ApiLoggerService;
use App\Services\DocumentService;
use App\Services\ExternalApi\NubariumService;
use App\Services\KycServiceFactory;
use App\Services\SmsServiceFactory;
use App\Services\TwilioServiceFactory;
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

        // SmsServiceInterface: la factory resuelve el proveedor SMS activo del
        // tenant (Twilio o Nubarium) leyendo TenantApiConfig. Cae a Twilio
        // (no configurado) si no hay proveedor activo, para no romper
        // type-hints que esperan una instancia no nula.
        $this->app->bind(SmsServiceInterface::class, function ($app) {
            $tenantId = $app->bound('tenant.id') ? $app->make('tenant.id') : null;
            return (new SmsServiceFactory($tenantId))->forCurrentTenant();
        });

        $this->app->bind(SmsServiceFactory::class, function ($app) {
            $tenantId = $app->bound('tenant.id') ? $app->make('tenant.id') : null;
            return new SmsServiceFactory($tenantId);
        });

        // KycServiceInterface binding requires tenant context
        // NubariumService needs tenant for API credentials lookup
        $this->app->bind(KycServiceInterface::class, function ($app) {
            $tenant = $app->bound('tenant') ? $app->make('tenant') : null;
            return new NubariumService($tenant);
        });

        // Factory bindings with tenant injection from container
        $this->app->bind(KycServiceFactory::class, function ($app) {
            $tenant = $app->bound('tenant') ? $app->make('tenant') : null;
            return new KycServiceFactory($tenant);
        });

        $this->app->bind(TwilioServiceFactory::class, function ($app) {
            $tenantId = $app->bound('tenant.id') ? $app->make('tenant.id') : null;
            return new TwilioServiceFactory($tenantId);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->registerObservers();

        // Cachea los lookups de Sanctum en Redis 60s. Ahorra ~560ms por
        // request autenticado (2 queries: token + tokenable) contra la DB
        // remota. Tradeoff: tokens revocados siguen validos hasta 60s,
        // excepto en logout explicito (deleted hook invalida al instante).
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(CachedPersonalAccessToken::class);
    }

    /**
     * Observers que invalidan cache transversal cuando cambian datos
     * compartidos por multiples endpoints.
     */
    protected function registerObservers(): void
    {
        Product::observe(V2ConfigCacheObserver::class);
        TenantApiConfig::observe(V2ConfigCacheObserver::class);
        TenantBranding::observe(V2ConfigCacheObserver::class);
        NotificationTemplate::observe(V2ConfigCacheObserver::class);
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

        // KYC validation: 10 requests per minute per user (external API calls are expensive)
        RateLimiter::for('kyc', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // KYC biometric: 5 requests per minute (more expensive, sensitive)
        RateLimiter::for('kyc-biometric', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
