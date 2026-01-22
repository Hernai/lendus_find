<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuid, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'rfc',
        'branding',
        'settings',
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
        'webhook_config' => 'array',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

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
