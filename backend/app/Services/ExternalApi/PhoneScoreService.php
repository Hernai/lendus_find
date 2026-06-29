<?php

namespace App\Services\ExternalApi;

use App\Models\Tenant;
use App\Services\ExternalApi\Nubarium\NubariumRiskService;

/**
 * Phone Score (Nubarium Phone Risk). Wrapper legacy: delega en
 * NubariumRiskService (API Plus real). El flujo nuevo persiste el resultado vía
 * RunContactRiskJob → RiskAssessment; este wrapper se mantiene por compatibilidad.
 */
class PhoneScoreService
{
    /**
     * Retorna el score y nivel de riesgo del teléfono.
     *
     * @return array{score: ?int, risk: string, factors: array<int, string>}
     */
    public function score(string $phone, ?Tenant $tenant = null): array
    {
        $default = ['score' => null, 'risk' => 'UNKNOWN', 'factors' => []];

        if (! $tenant) {
            return $default;
        }

        $res = (new NubariumRiskService($tenant))->phoneRisk($phone);
        if (! ($res['success'] ?? false)) {
            return $default;
        }

        return [
            'score' => $res['score'] ?? null,
            'risk' => self::mapLevel($res['level'] ?? null),
            'factors' => array_filter([$res['recommendation'] ?? null]),
        ];
    }

    /** Normaliza el nivel de Nubarium (very-low/low/moderate/high) a LOW/MEDIUM/HIGH. */
    private static function mapLevel(?string $level): string
    {
        return match (strtolower((string) $level)) {
            'very-low', 'low' => 'LOW',
            'moderate', 'medium' => 'MEDIUM',
            'high', 'very-high' => 'HIGH',
            default => 'UNKNOWN',
        };
    }
}
