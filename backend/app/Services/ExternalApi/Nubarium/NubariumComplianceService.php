<?php

namespace App\Services\ExternalApi\Nubarium;

use Illuminate\Support\Facades\Log;

/**
 * Nubarium Compliance Service.
 *
 * Handles compliance and background check services:
 * - OFAC & UN sanctions check        (/blocklist/v1/query)
 * - PLD (Mexican AML) blacklists      (/blacklists/v1/consulta)
 * - SPEI CEP validation               (/banxico/v2/valida_cep)
 * - Professional license (SEP)        (/sep/obtener_cedula)
 * - IMSS history                      (async webhook — pendiente)
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
                $data = $response->json() ?? [];

                // La doc de /blocklist/v1/query devuelve sólo `records` (sin
                // `status` ni `validationCode`, a diferencia de blacklists/PLD).
                // Éxito = la consulta se ejecutó; `found` = hubo coincidencias.
                $records = $data['records'] ?? [];

                // Nubarium expone la coincidencia en `similarity`; el front
                // espera `score`. Lo exponemos sin perder los campos originales.
                $matches = array_map(static function ($r) {
                    if (is_array($r)) {
                        $r['score'] = $r['similarity'] ?? $r['score'] ?? null;
                    }
                    return $r;
                }, $records);

                return [
                    'success' => true,
                    'found' => !empty($matches),
                    'matches' => $matches,
                    'count' => count($matches),
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
            Log::error('Nubarium OFAC check error', ['error' => static::sanitizeError($e)]);

            return [
                'success' => false,
                'error' => 'Error al consultar OFAC: ' . static::sanitizeError($e),
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
            Log::error('Nubarium PLD blacklists check error', ['error' => static::sanitizeError($e)]);

            return [
                'success' => false,
                'error' => 'Error al consultar listas negras: ' . static::sanitizeError($e),
            ];
        }
    }

    /**
     * Historial IMSS (NSS / empleo).
     *
     * PENDIENTE — servicio ASÍNCRONO por webhook. En Nubarium estos endpoints
     * NO devuelven los datos en la misma respuesta: regresan un
     * `codigoValidacion` y publican el resultado a una URL de callback más tarde.
     *   POST /imss/wh/v1/obtener_nss          { curp, url }
     *   POST /mex/ss/v1/employment-info-imss  { curp, nss, url }
     * Habilitar requiere implementar un receptor de webhooks (ruta + modelo
     * para guardar el resultado async). Mientras tanto NO llamamos a Nubarium
     * para no dejar peticiones colgadas sin destino de callback.
     */
    public function getImssHistory(string $curp, ?string $nss = null): array
    {
        return [
            'success' => false,
            'code' => 'NOT_IMPLEMENTED',
            'error' => 'El historial IMSS es un servicio asíncrono (webhook) de Nubarium que aún no está disponible.',
        ];
    }

    /**
     * Valida un CEP (Comprobante Electrónico de Pago) SPEI contra BANXICO.
     *
     * Doc Nubarium (México → Banking → Validate CEP (SPEI)):
     *   POST /banxico/v2/valida_cep
     *   { tipoCriterio, fechaPago (dd-mm-aaaa), claveRastreo, institucionEmisora,
     *     institucionReceptora, cuentaBeneficiaria, montoPago }
     * Devuelve el comprobante en `speiTercero`.
     *
     * @param array<string, mixed> $data
     */
    public function validateCep(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $required = ['clave_rastreo', 'fecha_pago', 'institucion_emisora', 'institucion_receptora', 'cuenta_beneficiaria', 'monto'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "Campo requerido: {$field}"];
            }
        }

        $payload = [
            // "T" = clave de rastreo, "R" = número de referencia.
            'tipoCriterio' => $data['tipo_criterio'] ?? 'T',
            'fechaPago' => $this->formatBanxicoDate($data['fecha_pago']),
            'claveRastreo' => $data['clave_rastreo'],
            'institucionEmisora' => (string) $data['institucion_emisora'],
            'institucionReceptora' => (string) $data['institucion_receptora'],
            'cuentaBeneficiaria' => (string) $data['cuenta_beneficiaria'],
            'montoPago' => (string) $data['monto'],
        ];

        $this->logRequest('POST', 'banxico/v2/valida_cep', ['claveRastreo' => $payload['claveRastreo']]);

        try {
            $response = $this->apiCall('global', 'POST', '/banxico/v2/valida_cep', $payload, 60);

            $this->logResponse($response, 'banxico/v2/valida_cep');

            if ($response->successful()) {
                $result = $response->json() ?? [];
                // BANXICO devuelve el comprobante en `speiTercero`; su ausencia
                // significa que el CEP no pudo validarse (no es un error nuestro).
                $comprobante = $result['speiTercero'] ?? null;

                return [
                    'success' => true,
                    'valid' => !empty($comprobante),
                    'data' => $comprobante ?? $result,
                    'validation_code' => $result['codigoValidacion'] ?? null,
                ];
            }

            return $this->handleError($response, 'Validación de CEP');
        } catch (\Exception $e) {
            Log::error('Nubarium CEP validation error', ['error' => static::sanitizeError($e)]);

            return [
                'success' => false,
                'error' => 'Error al validar CEP: ' . static::sanitizeError($e),
            ];
        }
    }

    /**
     * Valida una Cédula Profesional contra el SEP.
     *
     * Doc (México → SEP → Validate Professional Certificate ID (SEP)):
     *   POST /sep/obtener_cedula  { numeroCedula, nombres?, apellidoPaterno?,
     *   apellidoMaterno?, tipoBusqueda? }
     * Nubarium responde 200 aun sin coincidencia; el éxito real es estatus OK.
     * Devuelve `cedulas[]` con institución, nombre y título.
     */
    public function validateCedulaProfesional(string $cedula): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $this->logRequest('POST', 'sep/obtener_cedula', ['numeroCedula' => $cedula]);

        try {
            $response = $this->apiCall('global', 'POST', '/sep/obtener_cedula', [
                'numeroCedula' => $cedula,
            ]);

            $this->logResponse($response, 'sep/obtener_cedula');

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $isOk = strtoupper((string) ($data['estatus'] ?? '')) === 'OK';
                $cedulas = $data['cedulas'] ?? [];
                $first = $cedulas[0] ?? [];

                return [
                    // La consulta se ejecutó; `valid` indica si hubo coincidencia.
                    'success' => true,
                    'valid' => $isOk && !empty($cedulas),
                    'data' => [
                        'cedula' => $first['cedula'] ?? $cedula,
                        'nombres' => $first['nombres'] ?? null,
                        'apellido_paterno' => $first['apellidoPaterno'] ?? null,
                        'apellido_materno' => $first['apellidoMaterno'] ?? null,
                        'titulo' => $first['titulo'] ?? null,
                        'institucion' => $first['institucion'] ?? null,
                        'tipo' => $first['tipo'] ?? null,
                        'sexo' => $first['sexo'] ?? null,
                    ],
                    'cedulas' => $cedulas,
                    'validation_code' => $data['codigoValidacion'] ?? null,
                ];
            }

            return $this->handleError($response, 'Validación de Cédula Profesional');
        } catch (\Exception $e) {
            Log::error('Nubarium Cedula validation error', ['error' => static::sanitizeError($e)]);

            return [
                'success' => false,
                'error' => 'Error al validar cédula: ' . static::sanitizeError($e),
            ];
        }
    }

    /**
     * Formatea una fecha al formato dd-mm-aaaa que exige BANXICO (valida_cep).
     */
    protected function formatBanxicoDate(string $date): string
    {
        try {
            return \Carbon\Carbon::parse($date)->format('d-m-Y');
        } catch (\Exception) {
            return $date;
        }
    }
}
