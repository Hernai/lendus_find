<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Tenant extends Model
{
    use HasFactory, HasUuid, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'legal_name',
        'rfc',
        'branding',
        'settings',
        'features',
        'webhook_config',
        'email',
        'phone',
        'website',
        'is_active',
        'activated_at',
        'suspended_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'branding' => 'array',
        'settings' => 'array',
        'features' => 'array',
        'webhook_config' => 'array',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /**
     * Invalidar el cache de lookup del middleware `IdentifyTenant` cuando
     * se cree, actualice o borre un tenant. Las keys vivas son por slug
     * y por UUID — invalidamos ambas porque IdentifyTenant las usa.
     */
    protected static function booted(): void
    {
        $forget = function (self $tenant): void {
            Cache::forget("tenant:lookup:{$tenant->id}");
            if ($tenant->slug) {
                Cache::forget("tenant:lookup:{$tenant->slug}");
            }
            // Si cambió el slug, también el viejo
            $originalSlug = $tenant->getOriginal('slug');
            if ($originalSlug && $originalSlug !== $tenant->slug) {
                Cache::forget("tenant:lookup:{$originalSlug}");
            }
            // Lookup por dominio público (tenants.domain) — IdentifyTenant
            // matchea el host exacto del request contra esta columna.
            if ($tenant->domain) {
                Cache::forget("tenant:domain:{$tenant->domain}");
                Cache::forget("tenant:lookup:{$tenant->domain}");
            }
            $originalDomain = $tenant->getOriginal('domain');
            if ($originalDomain && $originalDomain !== $tenant->domain) {
                Cache::forget("tenant:domain:{$originalDomain}");
                Cache::forget("tenant:lookup:{$originalDomain}");
            }
            // V2 public config: payload derivado del tenant (branding, settings,
            // integraciones). Si el tenant cambió, el config tiene que regenerarse.
            Cache::forget("v2:config:{$tenant->id}");
            // Lista global de tenants activos (usado por el selector del
            // super_admin en login/me/availableTenants).
            Cache::forget('tenants:active:selector');
            // /v2/staff/config (showCacheKey)
            Cache::forget("staff:config:show:{$tenant->id}");
        };

        static::saved($forget);
        static::deleted($forget);
        static::restored($forget);
    }

    /**
     * Lista cacheada de tenants activos para selectores (super_admin login,
     * me, /me/tenants). Antes cada uno corria una query a `tenants` y eso
     * sumaba 3x ~280ms por sesion del super_admin contra DB remota.
     *
     * TTL alto (10 min) porque la lista de tenants cambia raramente; las
     * altas/bajas/edits invalidan via Tenant::booted() forget.
     *
     * @return array<int, array{id:string,slug:string,name:string}>
     */
    public static function activeListForSelector(): array
    {
        return Cache::remember(
            'tenants:active:selector',
            600,
            fn () => static::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'slug', 'name'])
                ->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->name])
                ->all()
        );
    }

    /**
     * Get the staff accounts for this tenant.
     */
    public function staffAccounts(): HasMany
    {
        return $this->hasMany(StaffAccount::class);
    }

    /**
     * Get the products for this tenant.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the persons for this tenant.
     */
    public function persons(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /**
     * Get the applications for this tenant.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Get the branding configuration.
     */
    public function brandingConfig(): HasOne
    {
        return $this->hasOne(TenantBranding::class);
    }

    /**
     * Check si una feature flag está habilitada para este tenant.
     *
     * Flags reconocidas:
     *  - loan_portfolio
     *  - unified_consent_screen
     *  - phone_score_enabled
     *  - auto_disbursement
     */
    public function hasFeature(string $flag): bool
    {
        return (bool) data_get($this->features ?? [], $flag, false);
    }

    /**
     * Get the API configurations.
     */
    public function apiConfigs(): HasMany
    {
        return $this->hasMany(TenantApiConfig::class);
    }

    /**
     * Get API config for a specific provider and service.
     */
    public function getApiConfig(string $provider, string $serviceType): ?TenantApiConfig
    {
        return $this->apiConfigs()
            ->where('provider', $provider)
            ->where('service_type', $serviceType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the branding config or default.
     */
    public function getBrandingAttribute($value): array
    {
        $default = [
            'primary_color' => '#6366f1',
            'secondary_color' => '#10b981',
            'accent_color' => '#f59e0b',
            'logo_url' => null,
            'favicon_url' => null,
            'font_family' => 'Inter, sans-serif',
            'border_radius' => '12px',
        ];

        // Decode JSON if it's a string (before cast is applied)
        $parsed = is_string($value) ? json_decode($value, true) : $value;

        return array_merge($default, $parsed ?? []);
    }

    /**
     * Get the settings or default.
     */
    public function getSettingsAttribute($value): array
    {
        $default = [
            'otp_provider' => 'twilio',
            'kyc_provider' => 'mati',
            'max_loan_amount' => 500000,
            'min_loan_amount' => 5000,
            'currency' => 'MXN',
            'timezone' => 'America/Mexico_City',
        ];

        // Decode JSON if it's a string (before cast is applied)
        $parsed = is_string($value) ? json_decode($value, true) : $value;

        return array_merge($default, $parsed ?? []);
    }

    /**
     * Scope to active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find a tenant by slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
