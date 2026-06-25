<?php

namespace App\Services;

use App\Contracts\SmsServiceInterface;
use App\Models\Tenant;
use App\Models\TenantApiConfig;
use App\Services\ExternalApi\Nubarium\NubariumOtpService;
use App\Services\ExternalApi\TwilioService;

/**
 * Resuelve el proveedor de SMS/WhatsApp activo de un tenant.
 *
 * Antes el binding de SmsServiceInterface estaba hardcodeado a Twilio. Esta
 * factory lee la TenantApiConfig activa para el canal y devuelve la
 * implementación correcta (Twilio o Nubarium), permitiendo que cada SOFOM
 * elija su proveedor desde el admin de integraciones.
 */
class SmsServiceFactory
{
    public function __construct(protected ?string $currentTenantId = null) {}

    /**
     * Config activa del tenant para el canal (sms|whatsapp).
     */
    public function configFor(?string $tenantId, string $channel = 'sms'): ?TenantApiConfig
    {
        if (!$tenantId) {
            return null;
        }

        $serviceType = strtoupper($channel) === 'WHATSAPP' ? 'whatsapp' : 'sms';

        return TenantApiConfig::query()
            ->where('tenant_id', $tenantId)
            ->where('service_type', $serviceType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Servicio SMS/WhatsApp concreto del tenant para el canal pedido, o null
     * si no hay proveedor activo (el caller decide el fallback, ej. exponer
     * el código en modo dev).
     */
    public function forTenant(?string $tenantId, string $channel = 'sms'): ?SmsServiceInterface
    {
        $config = $this->configFor($tenantId, $channel);
        if (!$config) {
            return null;
        }

        return $this->makeForProvider($config->provider, $tenantId, $channel);
    }

    /**
     * Instancia el servicio según el nombre de proveedor.
     */
    public function makeForProvider(string $provider, ?string $tenantId, string $channel = 'sms'): ?SmsServiceInterface
    {
        return match ($provider) {
            'twilio' => new TwilioService($tenantId),
            'nubarium' => $this->makeNubarium($tenantId, $channel),
            default => null,
        };
    }

    private function makeNubarium(?string $tenantId, string $channel): ?NubariumOtpService
    {
        $tenant = $tenantId ? Tenant::withoutGlobalScopes()->find($tenantId) : null;
        if (!$tenant) {
            return null;
        }
        $serviceType = strtoupper($channel) === 'WHATSAPP' ? 'whatsapp' : 'sms';
        return new NubariumOtpService($tenant, $serviceType);
    }

    /**
     * Para el binding de contenedor: resuelve para el tenant actual, con
     * Twilio (no configurado) como fallback para no romper type-hints que
     * esperan una instancia no nula.
     */
    public function forCurrentTenant(string $channel = 'sms'): SmsServiceInterface
    {
        return $this->forTenant($this->currentTenantId, $channel)
            ?? new TwilioService($this->currentTenantId);
    }
}
