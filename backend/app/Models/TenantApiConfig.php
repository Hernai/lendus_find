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
        'fcm' => 'Firebase Cloud Messaging',
        'apns' => 'Apple Push Notifications',
        'nubarium_phone_score' => 'Nubarium Phone Score',
        'stp' => 'STP — Dispersión',
        'conekta' => 'Conekta — Cobranza',
        'openpay' => 'OpenPay — Cobranza',
        'google_maps' => 'Google Maps',
        'nominatim' => 'OpenStreetMap / Nominatim',
    ];

    /**
     * Service types.
     */
    public const SERVICE_TYPES = [
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'kyc' => 'KYC/Identidad',
        'bank_validation' => 'Validación bancaria',
        'credit_bureau' => 'Buró de Crédito',
        'document_validation' => 'Validación de Documentos',
        'push' => 'Push Notifications',
        'phone_score' => 'Phone Score',
        'loan_disbursement' => 'Dispersión de préstamos',
        'payment_collection' => 'Cobranza',
        'geocoding' => 'Geocodificación / Mapas',
        'sdk' => 'SDK biométrico',
        'phone_risk' => 'Riesgo telefónico',
        'email_risk' => 'Riesgo de correo',
    ];

    /**
     * Descripción de cada tipo de servicio: qué habilita el admin al activarlo.
     * Lo consume la UI de integraciones para que el usuario sepa qué está
     * habilitando. Single source of truth: este array.
     */
    public const SERVICE_TYPE_DESCRIPTIONS = [
        'sms' => 'Envío de SMS y códigos OTP por mensaje de texto.',
        'whatsapp' => 'Envío de mensajes y OTP por WhatsApp.',
        'email' => 'Envío de correos y códigos OTP por email.',
        'kyc' => 'Validación de identidad: CURP, RFC, INE y listas OFAC/PLD.',
        'bank_validation' => 'Validación de CLABE y tarjeta de débito contra el banco (titularidad de la cuenta).',
        'credit_bureau' => 'Consulta de historial en buró de crédito.',
        'document_validation' => 'Validación y OCR de documentos (comprobantes, identificaciones).',
        'push' => 'Notificaciones push a la app móvil.',
        'phone_score' => 'Score de riesgo del número telefónico.',
        'loan_disbursement' => 'Dispersión de préstamos a cuentas bancarias.',
        'payment_collection' => 'Cobranza y conciliación de pagos.',
        'geocoding' => 'Geolocalización y autollenado de domicilio (coordenadas → dirección) en el onboarding.',
        'sdk' => 'SDK biométrico móvil (captura de liveness/rostro en la app).',
        'phone_risk' => 'Riesgo del teléfono (score y nivel) tras confirmar el OTP.',
        'email_risk' => 'Riesgo del correo (score y deliverability) tras confirmar el OTP.',
    ];

    /**
     * Estado de implementación de cada proveedor:
     *   - 'available'   : integración real y probada (se puede usar en prod)
     *   - 'beta'        : implementación parcial / stub (devuelve mocks)
     *   - 'coming_soon' : sólo en el catálogo, sin código aún
     *
     * Lo consume el admin de integraciones para mostrar badges y deshabilitar
     * en el alta los que aún no funcionan. Single source of truth: este array.
     */
    public const PROVIDER_STATUS = [
        'twilio' => 'available',
        'nubarium' => 'available',
        'smtp' => 'available',
        'nubarium_phone_score' => 'beta',
        'stp' => 'beta',
        'conekta' => 'beta',
        'openpay' => 'beta',
        'google_maps' => 'available',
        'nominatim' => 'available',
        'messagebird' => 'coming_soon',
        'vonage' => 'coming_soon',
        'mailgun' => 'coming_soon',
        'sendgrid' => 'coming_soon',
        'ses' => 'coming_soon',
        'circulo_credito' => 'coming_soon',
        'mati' => 'coming_soon',
        'onfido' => 'coming_soon',
        'jumio' => 'coming_soon',
        'fcm' => 'coming_soon',
        'apns' => 'coming_soon',
    ];

    /**
     * Tipos de servicio que ofrece cada proveedor (claves de SERVICE_TYPES).
     *
     * Lo consume el admin de integraciones para que, tras elegir un proveedor,
     * sólo se muestren los servicios válidos de ESE proveedor (no la lista
     * completa). Single source of truth: este array.
     *
     * @var array<string, array<int, string>>
     */
    public const PROVIDER_SERVICES = [
        'twilio' => ['sms', 'whatsapp'],
        'messagebird' => ['sms', 'whatsapp'],
        'vonage' => ['sms', 'whatsapp'],
        // Nubarium OTP soporta SMS y Email; WhatsApp NO (ver NubariumOtpService).
        // 'bank_validation' (CLABE/débito) es API Plus, distinto a KYC/identidad.
        'nubarium' => ['kyc', 'sdk', 'bank_validation', 'sms', 'email', 'phone_risk', 'email_risk'],
        'smtp' => ['email'],
        'mailgun' => ['email'],
        'sendgrid' => ['email'],
        'ses' => ['email'],
        'circulo_credito' => ['credit_bureau'],
        'mati' => ['kyc', 'document_validation'],
        'onfido' => ['kyc', 'document_validation'],
        'jumio' => ['kyc', 'document_validation'],
        'fcm' => ['push'],
        'apns' => ['push'],
        'nubarium_phone_score' => ['phone_score'],
        'stp' => ['loan_disbursement'],
        'conekta' => ['payment_collection'],
        'openpay' => ['payment_collection'],
        'google_maps' => ['geocoding'],
        'nominatim' => ['geocoding'],
    ];

    /**
     * Estado de un proveedor (default 'coming_soon' si no está listado).
     */
    public static function statusFor(string $provider): string
    {
        return self::PROVIDER_STATUS[$provider] ?? 'coming_soon';
    }

    /**
     * Servicios soportados por un proveedor (claves de SERVICE_TYPES). Si el
     * proveedor no está mapeado, devuelve todas las claves (fallback seguro).
     *
     * @return array<int, string>
     */
    public static function servicesFor(string $provider): array
    {
        return self::PROVIDER_SERVICES[$provider] ?? array_keys(self::SERVICE_TYPES);
    }

    /**
     * Catálogo de proveedores con label + estado + servicios, para el alta del
     * admin (selección visual de proveedor y filtrado de tipo de servicio).
     *
     * @return array<int, array{key: string, label: string, status: string, services: array<int, string>}>
     */
    public static function providerCatalog(): array
    {
        return array_map(
            fn ($key, $label) => [
                'key' => $key,
                'label' => $label,
                'status' => self::statusFor($key),
                'services' => self::servicesFor($key),
            ],
            array_keys(self::PROVIDERS),
            array_values(self::PROVIDERS),
        );
    }

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
            'sendgrid', 'mati', 'google_maps' => !empty($this->api_key),
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
            'provider_status' => self::statusFor($this->provider),
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
