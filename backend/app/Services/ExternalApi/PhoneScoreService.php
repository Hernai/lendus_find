<?php

namespace App\Services\ExternalApi;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Stub de Phone Score (Nubarium Phone Risk).
 *
 * Devuelve un score de riesgo del teléfono del solicitante. Cuando
 * MoneyCapital provea credenciales reales de Nubarium Phone Score,
 * este servicio se conecta. Por ahora retorna LOW por defecto.
 *
 * Credenciales esperadas en TenantApiConfig:
 *  - provider='nubarium_phone_score', service_type='phone_score'
 *  - extra_config: { api_url, api_key }
 */
class PhoneScoreService
{
    /**
     * Retorna el score y nivel de riesgo del teléfono.
     *
     * @return array{score: int, risk: string, factors: array<int, string>}
     */
    public function score(string $phone, ?Tenant $tenant = null): array
    {
        $config = $tenant?->getApiConfig('nubarium_phone_score', 'phone_score');

        if (! $config || ! ($config->extra_config['api_url'] ?? null)) {
            Log::info('PhoneScore stub: returning default LOW risk', ['phone' => $phone]);
            return [
                'score' => 700,
                'risk' => 'LOW',
                'factors' => [],
            ];
        }

        // TODO: implementar llamada real a Nubarium Phone Risk.
        return [
            'score' => 700,
            'risk' => 'LOW',
            'factors' => [],
        ];
    }
}
