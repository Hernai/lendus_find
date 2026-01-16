<?php

namespace App\Services\ExternalApi\Nubarium;

use Illuminate\Support\Facades\Log;

/**
 * Nubarium Compliance Service.
 *
 * Handles compliance and background check services:
 * - OFAC & UN sanctions check
 * - PLD (Mexican anti-money laundering) blacklists
 * - IMSS employment history
 * - SPEI CEP validation
 * - Professional license validation (SEP)
 */
class NubariumComplianceService extends BaseNubariumService
{
    /**
     * Check OFAC & UN sanctions block lists.
     */
    public function checkOfac(string $name, int $similarity = 80): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $this->logRequest('POST', 'blocklist/v1/query', ['name' => $name, 'similarity' => $similarity]);

        try {
            $payload = [
                'name' => strtoupper($name),
                'similarity' => $similarity,
            ];

            $response = $this->apiCall('global', 'POST', '/blocklist/v1/query', $payload);

            $this->logResponse($response, 'blocklist/v1/query');

            if ($response->successful()) {
                $data = $response->json();

                $isOk = ($data['status'] ?? '') === 'OK';
                $records = $data['records'] ?? [];
                $found = !empty($records);

                return [
                    'success' => $isOk,
                    'found' => $found,
                    'matches' => $records,
                    'count' => count($records),
                    'validation_code' => $data['validationCode'] ?? null,
                    'checked_at' => now()->toISOString(),
                ];
            }

            // Service not available - allow flow to continue
            if ($response->status() === 404) {
                Log::warning('Nubarium OFAC blocklist service not available (404)', [
                    'tenant_id' => $this->tenant->id,
                ]);

                return [
                    'success' => true,
                    'found' => false,
                    'matches' => [],
                    'count' => 0,
                    'checked_at' => now()->toISOString(),
                    'warning' => 'Servicio de listas OFAC no disponible',
                ];
            }

            return $this->handleError($response, 'Consulta OFAC');
        } catch (\Exception $e) {
            Log::error('Nubarium OFAC check error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al consultar OFAC: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check Mexican PLD blacklists.
     */
    public function checkPldBlacklists(string $fullName, ?string $curp = null, int $similarity = 80): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $this->logRequest('POST', 'blacklists/v1/consulta', [
            'nombreCompleto' => $fullName,
            'curp' => $curp,
            'similitud' => $similarity,
        ]);

        try {
            $payload = [
                'nombreCompleto' => strtoupper($fullName),
                'similitud' => $similarity,
            ];

            if ($curp) {
                $payload['curp'] = strtoupper($curp);
            }

            $response = $this->apiCall('global', 'POST', '/blacklists/v1/consulta', $payload);

            $this->logResponse($response, 'blacklists/v1/consulta');

            if ($response->successful()) {
                $data = $response->json();

                $isOk = ($data['estatus'] ?? '') === 'OK';
                $results = $data['resultados'] ?? [];
                $found = !empty($results);

                return [
                    'success' => $isOk,
                    'found' => $found,
                    'matches' => $results,
                    'count' => $data['conteoResultados'] ?? count($results),
                    'validation_code' => $data['codigoValidacion'] ?? null,
                    'checked_at' => now()->toISOString(),
                ];
            }

            // Service not available - allow flow to continue
            if ($response->status() === 404) {
                Log::warning('Nubarium PLD blacklists service not available (404)', [
                    'tenant_id' => $this->tenant->id,
                ]);

                return [
                    'success' => true,
                    'found' => false,
                    'matches' => [],
                    'count' => 0,
                    'checked_at' => now()->toISOString(),
                    'warning' => 'Servicio de listas negras PLD no disponible',
                ];
            }

            return $this->handleError($response, 'Consulta Listas Negras PLD');
        } catch (\Exception $e) {
            Log::error('Nubarium PLD blacklists check error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al consultar listas negras: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get IMSS employment history.
     */
    public function getImssHistory(string $curp, ?string $nss = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $this->logRequest('POST', 'imss/history', ['curp' => $curp]);

        try {
            $payload = ['curp' => strtoupper($curp)];
            if ($nss) {
                $payload['nss'] = $nss;
            }

            $response = $this->apiCall('global', 'POST', '/imss/history', $payload, 60);

            $this->logResponse($response, 'imss/history');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'data' => [
                        'nss' => $data['nss'] ?? null,
                        'curp' => $data['curp'] ?? $curp,
                        'nombre' => $data['nombre'] ?? null,
                        'semanas_cotizadas' => $data['semanas_cotizadas'] ?? null,
                        'vigencia_derechos' => $data['vigencia_derechos'] ?? null,
                        'empleadores' => $data['empleadores'] ?? $data['employers'] ?? [],
                        'ultimo_movimiento' => $data['ultimo_movimiento'] ?? null,
                        'salario_base' => $data['salario_base'] ?? null,
                    ],
                    'raw_response' => $data,
                ];
            }

            return $this->handleError($response, 'Consulta historial IMSS');
        } catch (\Exception $e) {
            Log::error('Nubarium IMSS history error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al consultar IMSS: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate SPEI CEP (Comprobante Electrónico de Pago).
     */
    public function validateCep(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $required = ['clave_rastreo', 'fecha_operacion', 'monto'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "Campo requerido: {$field}"];
            }
        }

        $this->logRequest('POST', 'spei/cep', $data);

        try {
            $response = $this->apiCall('global', 'POST', '/spei/cep', [
                'clave_rastreo' => $data['clave_rastreo'],
                'fecha_operacion' => $this->formatDate($data['fecha_operacion']),
                'monto' => (float) $data['monto'],
                'cuenta_beneficiario' => $data['cuenta_beneficiario'] ?? null,
                'cuenta_ordenante' => $data['cuenta_ordenante'] ?? null,
            ]);

            $this->logResponse($response, 'spei/cep');

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'valid' => $result['valid'] ?? true,
                    'data' => $result,
                ];
            }

            return $this->handleError($response, 'Validación de CEP');
        } catch (\Exception $e) {
            Log::error('Nubarium CEP validation error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al validar CEP: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate professional license (Cédula Profesional).
     */
    public function validateCedulaProfesional(string $cedula): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $this->logRequest('POST', 'sep/cedula', ['cedula' => $cedula]);

        try {
            $response = $this->apiCall('global', 'POST', '/sep/cedula', [
                'cedula' => $cedula,
            ]);

            $this->logResponse($response, 'sep/cedula');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'valid' => $data['valid'] ?? true,
                    'data' => [
                        'cedula' => $data['cedula'] ?? $cedula,
                        'nombre' => $data['nombre'] ?? null,
                        'profesion' => $data['profesion'] ?? $data['profession'] ?? null,
                        'institucion' => $data['institucion'] ?? null,
                        'tipo' => $data['tipo'] ?? null,
                        'fecha_expedicion' => $data['fecha_expedicion'] ?? null,
                    ],
                    'raw_response' => $data,
                ];
            }

            return $this->handleError($response, 'Validación de Cédula Profesional');
        } catch (\Exception $e) {
            Log::error('Nubarium Cedula validation error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al validar cédula: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Format date to DD/MM/YYYY.
     */
    protected function formatDate(string $date): string
    {
        try {
            $parsed = \Carbon\Carbon::parse($date);
            return $parsed->format('d/m/Y');
        } catch (\Exception $e) {
            return $date;
        }
    }
}
