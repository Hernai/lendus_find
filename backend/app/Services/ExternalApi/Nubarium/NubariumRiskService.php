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

            $data = $response->json() ?? [];

            if (!$response->successful()) {
                return ['success' => false, 'error' => $this->extractError($data, 'Phone risk HTTP ' . $response->status()), 'raw' => $data ?: null];
            }

            if (strtoupper($this->asString($data['status'] ?? '') ?? '') === 'ERROR') {
                return ['success' => false, 'error' => $this->extractError($data, 'Error en phone risk'), 'raw' => $data];
            }

            $risk = is_array($data['risk'] ?? null) ? $data['risk'] : [];

            return [
                'success' => true,
                'score' => $this->asScalar($risk['score'] ?? null),
                'level' => $this->asString($risk['level'] ?? null),                 // very-low / low / moderate / high
                'recommendation' => $this->asString($risk['recommendation'] ?? null), // allow / review / deny
                'phone_type' => $this->asString($data['phone_type']['description'] ?? null),
                'carrier' => $this->asString($data['carrier']['name'] ?? null),
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

            $data = $response->json() ?? [];

            if (!$response->successful()) {
                return ['success' => false, 'error' => $this->extractError($data, 'Email risk HTTP ' . $response->status()), 'raw' => $data ?: null];
            }

            if (strtoupper($this->asString($data['status'] ?? '') ?? '') === 'ERROR') {
                return ['success' => false, 'error' => $this->extractError($data, 'Error en email risk'), 'raw' => $data];
            }

            $result = $data['query']['results'][0] ?? [];
            $result = is_array($result) ? $result : [];

            return [
                'success' => true,
                'score' => isset($result['EAScore']) && is_scalar($result['EAScore']) ? (int) $result['EAScore'] : null,
                'level' => $this->asString($result['fraudRisk'] ?? null),           // "051 Very Low"
                'deliverability' => $this->asString($result['status'] ?? null),     // "Verified"
                'country' => $this->asString($result['country'] ?? null),
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

    /**
     * Extrae un mensaje de error legible del body de Nubarium. El campo puede
     * venir como string o como objeto/array; nunca devolvemos un array (evita
     * "Array to string conversion" al persistir en risk_assessments).
     */
    private function extractError(array $data, string $default): string
    {
        return $this->asString($data['message'] ?? $data['mensaje'] ?? $data['error'] ?? null) ?? $default;
    }

    /** Devuelve un string (o null); si el valor es array/objeto, null. */
    private function asString(mixed $v): ?string
    {
        if ($v === null || is_string($v)) {
            return $v;
        }

        return is_scalar($v) ? (string) $v : null;
    }

    /** Devuelve un escalar (número/string/bool) o null si es array/objeto. */
    private function asScalar(mixed $v): mixed
    {
        return is_scalar($v) ? $v : null;
    }
}
