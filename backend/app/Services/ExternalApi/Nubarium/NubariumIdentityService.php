<?php

namespace App\Services\ExternalApi\Nubarium;

use Illuminate\Support\Facades\Log;

/**
 * Nubarium Identity Service.
 *
 * Handles identity validation services:
 * - CURP validation (RENAPO)
 * - RFC validation (SAT)
 */
class NubariumIdentityService extends BaseNubariumService
{
    /**
     * Mexican states for CURP generation.
     */
    public const BIRTH_STATES = [
        'AS' => 'Aguascalientes',
        'BC' => 'Baja California',
        'BS' => 'Baja California Sur',
        'CC' => 'Campeche',
        'CL' => 'Coahuila',
        'CM' => 'Colima',
        'CS' => 'Chiapas',
        'CH' => 'Chihuahua',
        'DF' => 'Ciudad de México',
        'DG' => 'Durango',
        'GT' => 'Guanajuato',
        'GR' => 'Guerrero',
        'HG' => 'Hidalgo',
        'JC' => 'Jalisco',
        'MC' => 'Estado de México',
        'MN' => 'Michoacán',
        'MS' => 'Morelos',
        'NT' => 'Nayarit',
        'NL' => 'Nuevo León',
        'OC' => 'Oaxaca',
        'PL' => 'Puebla',
        'QT' => 'Querétaro',
        'QR' => 'Quintana Roo',
        'SP' => 'San Luis Potosí',
        'SL' => 'Sinaloa',
        'SR' => 'Sonora',
        'TC' => 'Tabasco',
        'TS' => 'Tamaulipas',
        'TL' => 'Tlaxcala',
        'VZ' => 'Veracruz',
        'YN' => 'Yucatán',
        'ZS' => 'Zacatecas',
        'NE' => 'Nacido en el Extranjero',
    ];

    /**
     * Validate a CURP and retrieve associated data from RENAPO.
     */
    public function validateCurp(string $curp): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        if (!$this->isValidCurpFormat($curp)) {
            return [
                'success' => false,
                'error' => 'Formato de CURP inválido',
                'valid' => false,
            ];
        }

        $this->logRequest('POST', 'renapo/v3/valida_curp', ['curp' => $curp]);

        try {
            $response = $this->apiCall('curp', 'POST', '/renapo/v3/valida_curp', [
                'curp' => strtoupper($curp),
            ]);

            $this->logResponse($response, 'renapo/v3/valida_curp');

            if ($response->successful()) {
                $data = $response->json();
                $isValid = ($data['estatus'] ?? '') === 'OK';

                if (!$isValid) {
                    return [
                        'success' => false,
                        'valid' => false,
                        'error' => $data['mensaje'] ?? 'CURP no válido',
                        'error_code' => $data['codigoMensaje'] ?? null,
                        'validation_code' => $data['codigoValidacion'] ?? null,
                    ];
                }

                $docData = $data['datosDocProbatorio'] ?? [];

                return [
                    'success' => true,
                    'valid' => true,
                    'validation_code' => $data['codigoValidacion'] ?? null,
                    'data' => [
                        'curp' => $data['curp'] ?? $curp,
                        'nombres' => $data['nombre'] ?? null,
                        'apellido_paterno' => $data['apellidoPaterno'] ?? null,
                        'apellido_materno' => $data['apellidoMaterno'] ?? null,
                        'fecha_nacimiento' => $data['fechaNacimiento'] ?? null,
                        'sexo' => $data['sexo'] ?? null,
                        'pais_nacimiento' => $data['paisNacimiento'] ?? 'MEXICO',
                        'estado_nacimiento' => $data['estadoNacimiento'] ?? null,
                        'documento_probatorio' => $data['docProbatorio'] ?? null,
                        'status_curp' => $data['estatusCurp'] ?? 'RCN',
                        'entidad_registro' => $docData['entidadRegistro'] ?? null,
                        'municipio_registro' => $docData['municipioRegistro'] ?? null,
                        'anio_registro' => $docData['anioReg'] ?? null,
                        'numero_acta' => $docData['numActa'] ?? null,
                        'foja' => $docData['foja'] ?? null,
                        'tomo' => $docData['tomo'] ?? null,
                        'libro' => $docData['libro'] ?? null,
                    ],
                    'raw_response' => $data,
                ];
            }

            return $this->handleError($response, 'Validación de CURP');
        } catch (\Exception $e) {
            Log::error('Nubarium CURP validation error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al validar CURP: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get CURP by personal data.
     */
    public function getCurp(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $required = ['nombres', 'apellido_paterno', 'fecha_nacimiento', 'sexo', 'entidad_nacimiento'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "Campo requerido: {$field}"];
            }
        }

        $payload = [
            'nombres' => strtoupper($data['nombres']),
            'primerApellido' => strtoupper($data['apellido_paterno']),
            'segundoApellido' => strtoupper($data['apellido_materno'] ?? ''),
            'fechaNacimiento' => $this->formatDate($data['fecha_nacimiento']),
            'sexo' => strtoupper(substr($data['sexo'], 0, 1)),
            'entidad' => strtoupper($data['entidad_nacimiento']),
        ];

        $this->logRequest('POST', 'renapo/obtener_curp', $payload);

        try {
            $response = $this->apiCall('curp', 'POST', '/renapo/obtener_curp', $payload);

            $this->logResponse($response, 'renapo/obtener_curp');

            if ($response->successful()) {
                $result = $response->json();
                $isValid = ($result['estatus'] ?? '') === 'OK';

                if (!$isValid) {
                    return [
                        'success' => false,
                        'error' => $result['mensaje'] ?? 'No se encontró CURP',
                        'error_code' => $result['codigoMensaje'] ?? null,
                    ];
                }

                return [
                    'success' => true,
                    'curp' => $result['curp'] ?? null,
                    'validation_code' => $result['codigoValidacion'] ?? null,
                    'data' => $result,
                ];
            }

            return $this->handleError($response, 'Obtención de CURP');
        } catch (\Exception $e) {
            Log::error('Nubarium getCurp error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al obtener CURP: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate RFC with SAT.
     */
    public function validateRfc(string $rfc): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        if (!$this->isValidRfcFormat($rfc)) {
            return [
                'success' => false,
                'error' => 'Formato de RFC inválido',
                'valid' => false,
            ];
        }

        $this->logRequest('POST', 'sat/valida_rfc', ['rfc' => $rfc]);

        try {
            $response = $this->apiCall('sat', 'POST', '/sat/valida_rfc', [
                'rfc' => strtoupper($rfc),
            ]);

            $this->logResponse($response, 'sat/valida_rfc');

            if ($response->successful()) {
                $data = $response->json();
                $isValid = ($data['estatus'] ?? '') === 'OK';

                if (!$isValid) {
                    return [
                        'success' => false,
                        'valid' => false,
                        'error' => $data['mensaje'] ?? 'RFC no válido',
                        'error_code' => $data['claveMensaje'] ?? null,
                        'validation_code' => $data['codigoValidacion'] ?? null,
                    ];
                }

                return [
                    'success' => true,
                    'valid' => true,
                    'validation_code' => $data['codigoValidacion'] ?? null,
                    'data' => [
                        'rfc' => $rfc,
                        'mensaje' => $data['mensaje'] ?? null,
                        'informacion_adicional' => $data['informacionAdicional'] ?? null,
                        'tipo_persona' => $data['tipoPersona'] ?? (strlen($rfc) === 12 ? 'M' : 'F'),
                        'tipo_persona_label' => ($data['tipoPersona'] ?? (strlen($rfc) === 12 ? 'M' : 'F')) === 'M' ? 'MORAL' : 'FISICA',
                    ],
                    'raw_response' => $data,
                ];
            }

            return $this->handleError($response, 'Validación de RFC');
        } catch (\Exception $e) {
            Log::error('Nubarium RFC validation error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al validar RFC: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate CURP format.
     */
    public function isValidCurpFormat(string $curp): bool
    {
        $pattern = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9][0-9]$/i';
        return strlen($curp) === 18 && preg_match($pattern, $curp);
    }

    /**
     * Validate RFC format.
     */
    public function isValidRfcFormat(string $rfc): bool
    {
        $length = strlen($rfc);

        if ($length !== 12 && $length !== 13) {
            return false;
        }

        $pattern = $length === 13
            ? '/^[A-Z]{4}[0-9]{6}[A-Z0-9]{3}$/i'
            : '/^[A-Z]{3}[0-9]{6}[A-Z0-9]{3}$/i';

        return preg_match($pattern, $rfc);
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

    /**
     * Get Mexican states for CURP.
     */
    public function getBirthStates(): array
    {
        return self::BIRTH_STATES;
    }
}
