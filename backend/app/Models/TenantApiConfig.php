<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TenantApiConfig extends Model
{
    use HasUuid, HasAuditFields;

    protected $fillable = [
        'tenant_id',
        'provider',
        'service_type',
        'api_key',
        'api_secret',
        'account_sid',
        'auth_token',
        'from_number',
        'from_email',
        'domain',
        'webhook_url',
        'webhook_secret',
        'extra_config',
        'is_active',
        'is_sandbox',
        'last_tested_at',
        'last_test_success',
        'last_test_error',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'extra_config' => 'array',
        'is_active' => 'boolean',
        'is_sandbox' => 'boolean',
        'last_tested_at' => 'datetime',
        'last_test_success' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
        'account_sid',
        'auth_token',
        'webhook_secret',
    ];

    /**
     * Known providers.
     */
    public const PROVIDERS = [
        'twilio' => 'Twilio',
        'messagebird' => 'MessageBird',
        'vonage' => 'Vonage',
        'mailgun' => 'Mailgun',
        'sendgrid' => 'SendGrid',
        'ses' => 'Amazon SES',
        'nubarium' => 'Nubarium',
        'circulo_credito' => 'Círculo de Crédito',
        'mati' => 'Mati (Metamap)',
        'onfido' => 'Onfido',
        'jumio' => 'Jumio',
        'smtp' => 'SMTP (Correo propio)',
    ];

    /**
     * Service types.
     */
    public const SERVICE_TYPES = [
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'kyc' => 'KYC/Identidad',
        'credit_bureau' => 'Buró de Crédito',
        'document_validation' => 'Validación de Documentos',
    ];

    /**
     * Get the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Encrypt sensitive fields before saving.
     */
    public function setApiKeyAttribute($value): void
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getApiKeyAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setApiSecretAttribute($value): void
    {
        $this->attributes['api_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getApiSecretAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setAccountSidAttribute($value): void
    {
        $this->attributes['account_sid'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccountSidAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setAuthTokenAttribute($value): void
    {
        $this->attributes['auth_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAuthTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Check if credentials are configured.
     */
    public function hasCredentials(): bool
    {
        return match ($this->provider) {
            'twilio' => !empty($this->account_sid) && !empty($this->auth_token),
            'mailgun' => !empty($this->api_key) && !empty($this->domain),
            'sendgrid', 'mati' => !empty($this->api_key),
            'nubarium', 'circulo_credito' => !empty($this->api_key) && !empty($this->api_secret),
            'smtp' => !empty($this->extra_config['host']) && !empty($this->extra_config['port']),
            default => !empty($this->api_key),
        };
    }

    /**
     * Get masked credentials for display.
     */
    public function getMaskedCredentials(): array
    {
        $mask = fn($val) => $val ? str_repeat('•', 8) . substr($val, -4) : null;

        return [
            'api_key' => $mask($this->api_key),
            'api_secret' => $mask($this->api_secret),
            'account_sid' => $mask($this->account_sid),
            'auth_token' => $mask($this->auth_token),
        ];
    }

    /**
     * Get for API response (safe).
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'provider_label' => self::PROVIDERS[$this->provider] ?? $this->provider,
            'service_type' => $this->service_type,
            'service_type_label' => self::SERVICE_TYPES[$this->service_type] ?? $this->service_type,
            'from_number' => $this->from_number,
            'from_email' => $this->from_email,
            'domain' => $this->domain,
            'is_active' => $this->is_active,
            'is_sandbox' => $this->is_sandbox,
            'has_credentials' => $this->hasCredentials(),
            'masked_credentials' => $this->getMaskedCredentials(),
            'extra_config' => $this->extra_config,
            'last_tested_at' => $this->last_tested_at?->toISOString(),
            'last_test_success' => $this->last_test_success,
            'last_test_error' => $this->last_test_error,
        ];
    }
}
