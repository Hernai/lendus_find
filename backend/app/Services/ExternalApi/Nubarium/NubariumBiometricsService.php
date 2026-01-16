<?php

namespace App\Services\ExternalApi\Nubarium;

use Illuminate\Support\Facades\Log;

/**
 * Nubarium Biometrics Service.
 *
 * Handles biometric and document verification:
 * - INE/IFE OCR extraction
 * - INE validation against nominal list
 * - Face matching (selfie vs INE)
 * - Liveness detection
 * - Biometric SDK token generation
 */
class NubariumBiometricsService extends BaseNubariumService
{
    /**
     * Extract data from INE/IFE using OCR.
     */
    public function extractIneData(string $frontImage, ?string $backImage = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $payload = ['id' => $frontImage];
        if ($backImage) {
            $payload['idReverso'] = $backImage;
        }

        $this->logRequest('POST', 'ocr/v1/obtener_datos_id', ['has_front' => true, 'has_back' => !empty($backImage)]);

        try {
            $response = $this->apiCall('ocr', 'POST', '/ocr/v1/obtener_datos_id', $payload, 60);

            $this->logResponse($response, 'ocr/v1/obtener_datos_id');

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Nubarium OCR raw response', [
                    'nombres' => $data['nombres'] ?? 'NOT_FOUND',
                    'primerApellido' => $data['primerApellido'] ?? 'NOT_FOUND',
                    'curp' => $data['curp'] ?? 'NOT_FOUND',
                    'all_keys' => array_keys($data),
                ]);

                if (isset($data['estatus']) && $data['estatus'] === 'ERROR') {
                    return [
                        'success' => false,
                        'error' => $data['mensaje'] ?? 'Error al procesar imagen',
                        'error_code' => $data['codigoMensaje'] ?? null,
                    ];
                }

                return [
                    'success' => true,
                    'validation_code' => $data['codigoValidacion'] ?? null,
                    'data' => [
                        'tipo' => $data['tipo'] ?? null,
                        'subtipo' => $data['subTipo'] ?? null,
                        'clave_elector' => $data['claveElector'] ?? null,
                        'curp' => $data['curp'] ?? null,
                        'nombres' => $data['nombres'] ?? null,
                        'apellido_paterno' => $data['primerApellido'] ?? null,
                        'apellido_materno' => $data['segundoApellido'] ?? null,
                        'fecha_nacimiento' => $data['fechaNacimiento'] ?? null,
                        'sexo' => $data['sexo'] ?? null,
                        'ocr' => $data['ocr'] ?? null,
                        'cic' => $data['cic'] ?? null,
                        'identificador_ciudadano' => $data['identificadorCiudadano'] ?? null,
                        'seccion' => $data['seccion'] ?? null,
                        'localidad' => $data['localidad'] ?? null,
                        'municipio' => $data['municipio'] ?? null,
                        'estado' => $data['estado'] ?? null,
                        'emision' => $data['emision'] ?? null,
                        'vigencia' => $data['vigencia'] ?? null,
                        'registro' => $data['registro'] ?? null,
                        'calle' => $data['calle'] ?? null,
                        'colonia' => $data['colonia'] ?? null,
                        'ciudad' => $data['ciudad'] ?? null,
                        'mrz' => $data['mrz'] ?? null,
                        'codigo_barras' => $data['codigoBarras'] ?? null,
                        'validacion_mrz' => $data['validacionMRZ'] ?? null,
                    ],
                    'raw_response' => $data,
                ];
            }

            return $this->handleError($response, 'Extracción de datos INE');
        } catch (\Exception $e) {
            Log::error('Nubarium INE OCR error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al extraer datos de INE: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate INE/IFE against the INE nominal list.
     */
    public function validateIneAgainstList(array $ineData): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $subtipo = strtoupper($ineData['subtipo'] ?? '');
        $payload = [];

        Log::info('Nubarium INE validation - building payload', [
            'subtipo' => $subtipo,
            'has_cic' => !empty($ineData['cic']),
            'has_identificador_ciudadano' => !empty($ineData['identificador_ciudadano']),
            'has_ocr' => !empty($ineData['ocr']),
            'has_clave_elector' => !empty($ineData['clave_elector']),
        ]);

        // Build payload based on INE type
        if (in_array($subtipo, ['E', 'F', 'G', 'H'])) {
            if (!empty($ineData['cic']) && !empty($ineData['identificador_ciudadano'])) {
                $payload = [
                    'cic' => $ineData['cic'],
                    'identificadorCiudadano' => $ineData['identificador_ciudadano'],
                ];
            }
        } elseif ($subtipo === 'D') {
            if (!empty($ineData['cic'])) {
                $payload = ['cic' => $ineData['cic']];
                if (!empty($ineData['ocr'])) {
                    $payload['ocr'] = $ineData['ocr'];
                }
            }
        } elseif ($subtipo === 'C') {
            if (!empty($ineData['clave_elector'])) {
                $payload = ['claveElector' => $ineData['clave_elector']];
                if (!empty($ineData['numero_emision'])) {
                    $payload['numeroEmision'] = $ineData['numero_emision'];
                }
                if (!empty($ineData['ocr'])) {
                    $payload['ocr'] = $ineData['ocr'];
                }
            }
        } else {
            // Unknown type - try best combination
            if (!empty($ineData['cic']) && !empty($ineData['identificador_ciudadano'])) {
                $payload = [
                    'cic' => $ineData['cic'],
                    'identificadorCiudadano' => $ineData['identificador_ciudadano'],
                ];
            } elseif (!empty($ineData['cic'])) {
                $payload = ['cic' => $ineData['cic']];
                if (!empty($ineData['ocr'])) {
                    $payload['ocr'] = $ineData['ocr'];
                }
            } elseif (!empty($ineData['clave_elector'])) {
                $payload = ['claveElector' => $ineData['clave_elector']];
                if (!empty($ineData['numero_emision'])) {
                    $payload['numeroEmision'] = $ineData['numero_emision'];
                }
            }
        }

        if (empty($payload)) {
            Log::warning('Nubarium INE validation - no valid payload', [
                'available_data' => array_keys(array_filter($ineData)),
            ]);
            return [
                'success' => false,
                'error' => 'No se pudieron obtener los datos necesarios del INE para validación.',
            ];
        }

        $this->logRequest('POST', 'ine/v2/valida_ine', $payload);

        try {
            $response = $this->apiCall('ine', 'POST', '/ine/v2/valida_ine', $payload);

            $this->logResponse($response, 'ine/v2/valida_ine');

            if ($response->successful()) {
                $data = $response->json();

                $status = $data['estatus'] ?? '';
                $isValid = $status === 'OK';
                $messageCode = $data['claveMensaje'] ?? null;

                return [
                    'success' => true,
                    'valid' => $isValid,
                    'validation_code' => $data['codigoValidacion'] ?? null,
                    'message' => $data['mensaje'] ?? null,
                    'message_code' => $messageCode,
                    'code' => $messageCode,
                    'data' => [
                        'clave_elector' => $data['claveElector'] ?? null,
                        'ocr' => $data['ocr'] ?? null,
                        'cic' => $data['cic'] ?? null,
                        'anio_registro' => $data['anioRegistro'] ?? null,
                        'anio_emision' => $data['anioEmision'] ?? null,
                        'vigencia' => $data['vigencia'] ?? null,
                        'numero_emision' => $data['numeroEmision'] ?? null,
                        'reporte_robo' => $data['reporteRobo'] ?? null,
                    ],
                    'raw_response' => $data,
                ];
            }

            return $this->handleError($response, 'Validación de INE contra lista nominal');
        } catch (\Exception $e) {
            Log::error('Nubarium INE validation error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error al validar INE: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Full INE validation: OCR + list validation.
     */
    public function validateIne(string $frontImage, ?string $backImage = null, bool $validateAgainstList = true): array
    {
        $ocrResult = $this->extractIneData($frontImage, $backImage);

        if (!$ocrResult['success']) {
            return $ocrResult;
        }

        $result = [
            'success' => true,
            'ocr_data' => $ocrResult['data'],
            'validation_code' => $ocrResult['validation_code'],
        ];

        if ($validateAgainstList && !empty($ocrResult['data'])) {
            $validationResult = $this->validateIneAgainstList($ocrResult['data']);
            $result['list_validation'] = $validationResult;
            $result['is_valid'] = $validationResult['valid'] ?? false;
        }

        return $result;
    }

    /**
     * Compare selfie with INE photo (Face Match).
     */
    public function validateFaceMatch(string $selfieImage, string $ineImage, int $threshold = 80): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        if (empty($selfieImage) || empty($ineImage)) {
            return [
                'success' => false,
                'error' => 'Se requieren ambas imágenes (selfie e INE)',
            ];
        }

        $this->logRequest('POST', 'global/biometrics/v1/compare-id-face', [
            'has_selfie' => true,
            'has_ine' => true,
            'threshold' => $threshold,
        ]);

        try {
            $payload = [
                'id' => $ineImage,
                'face' => $selfieImage,
                'media' => 'image',
                'threshold' => (string) $threshold,
            ];

            $response = $this->apiCall('global', 'POST', '/global/biometrics/v1/compare-id-face', $payload, 60);

            $this->logResponse($response, 'global/biometrics/v1/compare-id-face');

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Nubarium Face Match raw response', [
                    'status' => $data['status'] ?? $data['estatus'] ?? 'NOT_FOUND',
                    'similarity' => $data['similarity'] ?? $data['similitud'] ?? 'NOT_FOUND',
                    'messageCode' => $data['messageCode'] ?? $data['codigoMensaje'] ?? 'NOT_FOUND',
                ]);

                $status = $data['status'] ?? $data['estatus'] ?? '';
                $messageCode = $data['messageCode'] ?? $data['codigoMensaje'] ?? -1;

                if ($status === 'ERROR') {
                    return [
                        'success' => false,
                        'error' => $data['message'] ?? $data['mensaje'] ?? 'Error en comparación facial',
                        'error_code' => $messageCode,
                        'validation_code' => $data['validationCode'] ?? $data['codigoValidacion'] ?? null,
                    ];
                }

                $score = (float) ($data['similarity'] ?? $data['similitud'] ?? 0);
                $match = $messageCode === 0 && $score >= $threshold;

                return [
                    'success' => true,
                    'match' => $match,
                    'score' => $score,
                    'threshold' => $threshold,
                    'message_code' => $messageCode,
                    'validation_code' => $data['validationCode'] ?? $data['codigoValidacion'] ?? null,
                    'data' => [
                        'score' => $score,
                        'match' => $match,
                        'threshold' => $threshold,
                        'message' => $match
                            ? 'Los rostros coinciden'
                            : ($data['message'] ?? $data['mensaje'] ?? 'Los rostros no coinciden'),
                    ],
                    'raw_response' => $data,
                ];
            }

            return $this->handleError($response, 'Comparación facial (Face Match)');
        } catch (\Exception $e) {
            Log::error('Nubarium Face Match error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error en comparación facial: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate liveness detection from a selfie image.
     */
    public function validateLiveness(string $faceImage): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        if (empty($faceImage)) {
            return [
                'success' => false,
                'error' => 'Se requiere imagen del rostro',
            ];
        }

        $this->logRequest('POST', 'global/biometrics/v1/liveness-face', [
            'has_face' => true,
        ]);

        try {
            $response = $this->apiCall('global', 'POST', '/global/biometrics/v1/liveness-face', [
                'face' => $faceImage,
            ], 60);

            $this->logResponse($response, 'global/biometrics/v1/liveness-face');

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Nubarium Liveness raw response', [
                    'status' => $data['status'] ?? 'NOT_FOUND',
                    'messageCode' => $data['messageCode'] ?? 'NOT_FOUND',
                    'liveness' => $data['liveness'] ?? 'NOT_FOUND',
                ]);

                $status = $data['status'] ?? '';
                $messageCode = $data['messageCode'] ?? -1;

                if ($status === 'ERROR') {
                    return [
                        'success' => false,
                        'error' => $data['message'] ?? 'Error en detección de vida',
                        'error_code' => $messageCode,
                        'validation_code' => $data['validationCode'] ?? null,
                    ];
                }

                $livenessScore = (float) ($data['liveness'] ?? $data['score'] ?? 0);

                // Normalize to 0-100 if needed
                if ($livenessScore > 0 && $livenessScore <= 1) {
                    $livenessScore = $livenessScore * 100;
                }

                $passed = $messageCode === 0;

                return [
                    'success' => true,
                    'passed' => $passed,
                    'score' => $livenessScore,
                    'message_code' => $messageCode,
                    'validation_code' => $data['validationCode'] ?? null,
                    'data' => [
                        'score' => $livenessScore,
                        'passed' => $passed,
                        'message' => $passed
                            ? 'Prueba de vida exitosa'
                            : ($data['message'] ?? 'Prueba de vida fallida'),
                    ],
                    'raw_response' => $data,
                ];
            }

            return $this->handleError($response, 'Detección de vida (Liveness)');
        } catch (\Exception $e) {
            Log::error('Nubarium Liveness error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Error en detección de vida: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get JWT token for Biometric SDK.
     */
    public function getBiometricToken(string $transactionId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        $cacheKey = "nubarium_token_{$this->tenant->id}_{$transactionId}";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($transactionId) {
            $this->logRequest('POST', 'auth/token', ['transaction_id' => $transactionId]);

            try {
                $response = $this->serviceHttp('auth')->post('/auth/token', [
                    'transaction_id' => $transactionId,
                    'tenant_id' => $this->tenant->id,
                ]);

                $this->logResponse($response, 'auth/token');

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'success' => true,
                        'token' => $data['token'] ?? $data['jwt'] ?? null,
                        'expires_in' => $data['expires_in'] ?? 3600,
                        'transaction_id' => $transactionId,
                    ];
                }

                return $this->handleError($response, 'Generación de token biométrico');
            } catch (\Exception $e) {
                Log::error('Nubarium token generation error', ['error' => $e->getMessage()]);

                return [
                    'success' => false,
                    'error' => 'Error al generar token: ' . $e->getMessage(),
                ];
            }
        });
    }
}
