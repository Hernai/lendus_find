<?php

namespace App\Services\ExternalApi\Nubarium;

/**
 * Riesgo de contacto de Nubarium (API Plus): Phone Risk y Email Risk.
 *
 *   POST https://plus.nubarium.com/risk/v1/phone/query   { phone, email?, ip? }
 *   POST https://plus.nubarium.com/risk/v1/email/query   { email, ip? }
 *
 * Comparte la credencial Nubarium del tenant (loadConfig cae a cualquier config
 * Nubarium activa). Se invoca tras confirmar el OTP (ver RunContactRiskJob).
 */
class NubariumRiskService extends BaseNubariumService
{
    protected string $serviceType = 'phone_risk';

    /**
     * Riesgo del teléfono. Respuesta Nubarium: risk.{score,level,recommendation}.
     *
     * @return array<string, mixed>
     */
    public function phoneRisk(string $phone, ?string $email = null, ?string $ip = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $payload = array_filter([
            'phone' => $this->normalizePhone($phone),
            'email' => $email,
            'ip' => $ip,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $response = $this->apiCall('plus', 'POST', '/risk/v1/phone/query', $payload, 30);
            $this->logResponse($response, '/risk/v1/phone/query', 'plus');
            $data = $response->successful() ? ($response->json() ?? []) : [];

            if (strtoupper((string) ($data['status'] ?? '')) === 'ERROR') {
                return ['success' => false, 'error' => $data['message'] ?? 'Error en phone risk', 'raw' => $data];
            }

            $risk = $data['risk'] ?? [];

            return [
                'success' => true,
                'score' => $risk['score'] ?? null,
                'level' => $risk['level'] ?? null,                 // very-low / low / moderate / high
                'recommendation' => $risk['recommendation'] ?? null, // allow / review / deny
                'phone_type' => $data['phone_type']['description'] ?? null,
                'carrier' => $data['carrier']['name'] ?? null,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => static::sanitizeError($e)];
        }
    }

    /**
     * Riesgo del correo. Respuesta Nubarium: query.results[0].{EAScore,fraudRisk,status}.
     *
     * @return array<string, mixed>
     */
    public function emailRisk(string $email, ?string $ip = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $payload = array_filter([
            'email' => $email,
            'ip' => $ip,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $response = $this->apiCall('plus', 'POST', '/risk/v1/email/query', $payload, 30);
            $this->logResponse($response, '/risk/v1/email/query', 'plus');
            $data = $response->successful() ? ($response->json() ?? []) : [];

            if (strtoupper((string) ($data['status'] ?? '')) === 'ERROR') {
                return ['success' => false, 'error' => $data['message'] ?? 'Error en email risk', 'raw' => $data];
            }

            $result = $data['query']['results'][0] ?? [];

            return [
                'success' => true,
                'score' => isset($result['EAScore']) ? (int) $result['EAScore'] : null,
                'level' => $result['fraudRisk'] ?? null,           // "051 Very Low"
                'deliverability' => $result['status'] ?? null,     // "Verified"
                'country' => $result['country'] ?? null,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => static::sanitizeError($e)];
        }
    }

    /** Nubarium espera 52 + 10 dígitos (sin +). */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return strlen($digits) === 10 ? '52' . $digits : $digits;
    }
}
