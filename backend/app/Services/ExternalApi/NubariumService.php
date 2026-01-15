<?php

namespace App\Services\ExternalApi;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nubarium API Service for KYC/Identity Validation.
 *
 * Authentication flow:
 * 1. Basic Auth with username/password to generate JWT token
 * 2. Use Bearer token for all subsequent API calls
 *
 * Services available:
 * - CURP validation and retrieval (RENAPO)
 * - RFC validation (SAT)
 * - INE/IFE validation with OCR
 * - Biometric SDK token generation
 * - IMSS/ISSSTE history
 * - OFAC list consultation
 * - CEP SPEI validation
 * - Cédula Profesional (SEP)
 *
 * @see https://documenter.nubarium.com/
 */
class NubariumService extends BaseExternalApiService
{
    protected string $provider = 'nubarium';
    protected string $serviceType = 'kyc';

    /**
     * Nubarium API base URLs by service.
     * Each service has its own subdomain.
     */
    protected array $serviceUrls = [
        'auth' => 'https://api.nubarium.com',
        'curp' => 'https://curp.nubarium.com',
        'ine' => 'https://ine.nubarium.com',
        'ocr' => 'https://ocr.nubarium.com',
        'sat' => 'https://sat.nubarium.com',
        'global' => 'https://api.nubarium.com',
    ];

    /**
     * Sandbox URLs (if available).
     */
    protected array $sandboxUrls = [
        'auth' => 'https://api-sandbox.nubarium.com',
        'curp' => 'https://curp-sandbox.nubarium.com',
        'ine' => 'https://ine-sandbox.nubarium.com',
        'ocr' => 'https://ocr-sandbox.nubarium.com',
        'sat' => 'https://sat-sandbox.nubarium.com',
        'global' => 'https://api-sandbox.nubarium.com',
    ];

    /**
     * JWT token cache duration in seconds (slightly less than actual expiry).
     */
    protected int $tokenCacheDuration = 3500; // ~58 minutes (tokens usually expire in 1 hour)

    /**
     * Available validation services.
     */
    public const SERVICES = [
        'curp' => 'Validación de CURP',
        'rfc' => 'Validación de RFC',
        'ine' => 'Validación de INE',
        'cedula_sep' => 'Validación de Cédula Profesional SEP',
        'spei_cep' => 'Validación de CEP SPEI',
        'imss' => 'Historial IMSS',
        'issste' => 'Historial ISSSTE',
        'ofac' => 'Consulta Lista OFAC',
        'biometric_token' => 'Token para SDK Biométrico',
    ];

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

    public function __construct(Tenant $tenant)
    {
        parent::__construct($tenant);
    }

    /**
     * Get the username for Basic Auth (stored in api_key field).
     */
    protected function getUsername(): string
    {
        return $this->config?->api_key ?? '';
    }

    /**
     * Get the password for Basic Auth (stored in api_secret field).
     */
    protected function getPassword(): string
    {
        return $this->config?->api_secret ?? '';
    }

    /**
     * Track if we've already tried to refresh the token in this request.
     */
    protected bool $tokenRefreshAttempted = false;

    /**
     * Get or generate JWT token using Basic Auth.
     * Tokens are cached to avoid unnecessary API calls.
     */
    protected function getJwtToken(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $cacheKey = "nubarium_jwt_{$this->tenant->id}";

        return Cache::remember($cacheKey, $this->tokenCacheDuration, function () {
            return $this->generateJwtToken();
        });
    }

    /**
     * Force refresh JWT token (useful when token has expired).
     */
    public function refreshToken(): ?string
    {
        $this->clearTokenCache();
        $this->tokenRefreshAttempted = true;
        return $this->getJwtToken();
    }

    /**
     * Handle 401 Unauthorized or 403 Forbidden by refreshing token and retrying once.
     * Nubarium returns 403 when the JWT token has expired.
     */
    protected function handleUnauthorized(): ?string
    {
        if ($this->tokenRefreshAttempted) {
            Log::warning('Nubarium: Token refresh already attempted, not retrying', [
                'tenant_id' => $this->tenant->id,
            ]);
            return null;
        }

        Log::info('Nubarium: Attempting token refresh due to expired token (401/403)', [
            'tenant_id' => $this->tenant->id,
        ]);

        return $this->refreshToken();
    }

    /**
     * Generate a new JWT token from Nubarium using Basic Auth.
     */
    protected function generateJwtToken(): ?string
    {
        $username = $this->getUsername();
        $password = $this->getPassword();

        if (empty($username) || empty($password)) {
            Log::error('Nubarium: Missing credentials for JWT generation');
            return null;
        }

        // IMPORTANT: Nubarium does NOT have sandbox URLs - always use production
        // The sandbox mode only affects how the data is treated, not the API endpoint
        $baseUrl = $this->serviceUrls['auth']; // Always use production URL
        $tokenUrl = "{$baseUrl}/global/account/v1/generate-jwt";

        Log::info('Nubarium: Attempting JWT generation', [
            'tenant_id' => $this->tenant->id,
            'url' => $tokenUrl,
            'username' => substr($username, 0, 3) . '***',
        ]);

        $this->logRequest('POST', 'generate-jwt', ['expire' => 60]);

        try {
            $response = Http::withBasicAuth($username, $password)
                ->timeout(30)
                ->post($tokenUrl, [
                    'expire' => 60, // Token lifetime in minutes (max 60)
                ]);

            Log::info('Nubarium: JWT response received', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['bearer_token'] ?? $data['token'] ?? $data['access_token'] ?? null;

                if ($token) {
                    Log::info('Nubarium: JWT token generated successfully', [
                        'tenant_id' => $this->tenant->id,
                    ]);
                    return $token;
                }

                // Check if Nubarium returned an error in the response
                if (isset($data['status']) && $data['status'] === 'ERROR') {
                    Log::error('Nubarium: API returned error', [
                        'error' => $data['error'] ?? 'Unknown error',
                        'response' => $data,
                    ]);
                    return null;
                }

                Log::error('Nubarium: JWT response missing token', ['response' => $data]);
                return null;
            }

            // Log detailed error information
            Log::error('Nubarium: JWT generation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $tokenUrl,
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Nubarium: JWT generation exception', [
                'error' => $e->getMessage(),
                'url' => $tokenUrl,
            ]);
            return null;
        }
    }

    /**
     * Clear cached JWT token (useful when credentials change).
     */
    public function clearTokenCache(): void
    {
        $cacheKey = "nubarium_jwt_{$this->tenant->id}";
        Cache::forget($cacheKey);
    }

    /**
     * Get default headers with Bearer token authentication.
     */
    protected function getDefaultHeaders(): array
    {
        $token = $this->getJwtToken();

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $token ? "Bearer {$token}" : '',
        ];
    }

    /**
     * Get the base URL for a specific service.
     * Note: Nubarium does NOT have sandbox URLs - always uses production.
     */
    protected function getServiceUrl(string $service): string
    {
        // Nubarium always uses production URLs
        // The sandbox mode is only a flag in our system, not a different Nubarium environment
        return $this->serviceUrls[$service] ?? $this->serviceUrls['global'];
    }

    /**
     * Make an HTTP request to a specific service.
     */
    protected function serviceHttp(string $service): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->getServiceUrl($service))
            ->timeout($this->timeout)
            ->withHeaders($this->getDefaultHeaders());
    }

    /**
     * Make an API call with automatic token refresh on 401.
     *
     * @param string $service Service name (curp, ine, ocr, sat, global)
     * @param string $method HTTP method (GET, POST)
     * @param string $endpoint API endpoint
     * @param array $payload Request payload
     * @param int|null $timeout Custom timeout (optional)
     * @return \Illuminate\Http\Client\Response
     */
    protected function apiCall(string $service, string $method, string $endpoint, array $payload = [], ?int $timeout = null): \Illuminate\Http\Client\Response
    {
        $http = $this->serviceHttp($service);

        if ($timeout) {
            $http = $http->timeout($timeout);
        }

        $method = strtoupper($method);
        $response = $method === 'GET'
            ? $http->get($endpoint, $payload)
            : $http->post($endpoint, $payload);

        // If we get 401 Unauthorized or 403 Forbidden (expired token), try to refresh token and retry once
        if (($response->status() === 401 || $response->status() === 403) && !$this->tokenRefreshAttempted) {
            Log::info('Nubarium: Got ' . $response->status() . ', attempting token refresh', [
                'endpoint' => $endpoint,
                'tenant_id' => $this->tenant->id,
            ]);

            $newToken = $this->handleUnauthorized();

            if ($newToken) {
                // Retry with new token
                $http = $this->serviceHttp($service);
                if ($timeout) {
                    $http = $http->timeout($timeout);
                }

                $response = $method === 'GET'
                    ? $http->get($endpoint, $payload)
                    : $http->post($endpoint, $payload);

                Log::info('Nubarium: Retry after token refresh', [
                    'endpoint' => $endpoint,
                    'new_status' => $response->status(),
                    'successful' => $response->successful(),
                ]);
            }
        }

        return $response;
    }

    /**
     * Test API connection by attempting to generate a JWT token.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Error de configuración',
                'error' => 'Nubarium no está configurado para este tenant',
            ];
        }

        try {
            // Clear any cached token to force a fresh authentication
            $this->clearTokenCache();

            // Try to generate a new JWT token
            $token = $this->generateJwtToken();

            if ($token) {
                $this->updateTestResult(true, null);
                return [
                    'success' => true,
                    'message' => 'Conexión exitosa - Token obtenido',
                    'token_preview' => substr($token, 0, 20) . '...',
                ];
            }

            $this->updateTestResult(false, 'No se pudo generar el token JWT');
            return [
                'success' => false,
                'message' => 'Error de autenticación',
                'error' => 'Verifique las credenciales (usuario y contraseña)',
            ];
        } catch (\Exception $e) {
            $this->updateTestResult(false, $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error de conexión',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate a CURP and retrieve associated data from RENAPO.
     * Endpoint: curp.nubarium.com/renapo/v3/valida_curp
     *
     * @param string $curp The 18-character CURP to validate
     * @return array Validation result with person data
     */
    public function validateCurp(string $curp): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        // Validate CURP format first
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

                // Nubarium returns estatus: "OK" or "ERROR"
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

                // Parse documento probatorio data
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
                        // Datos del documento probatorio
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
     * Endpoint: curp.nubarium.com/renapo/obtener_curp
     *
     * @param array $data Personal data to search CURP
     * @return array Result with CURP if found
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

        // Map to Nubarium field names
        $payload = [
            'nombres' => strtoupper($data['nombres']),
            'primerApellido' => strtoupper($data['apellido_paterno']),
            'segundoApellido' => strtoupper($data['apellido_materno'] ?? ''),
            'fechaNacimiento' => $this->formatDate($data['fecha_nacimiento']),
            'sexo' => strtoupper(substr($data['sexo'], 0, 1)), // H or M
            'entidad' => strtoupper($data['entidad_nacimiento']), // 2-letter state code
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
     * Endpoint: sat.nubarium.com/sat/valida_rfc
     *
     * @param string $rfc The RFC to validate (12 or 13 characters)
     * @return array Validation result
     */
    public function validateRfc(string $rfc): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        // Validate RFC format
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

                // Nubarium returns estatus: "OK" or "ERROR"
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
     * Extract data from INE/IFE using OCR.
     * Endpoint: ocr.nubarium.com/ocr/v1/obtener_datos_id
     *
     * @param string $frontImage Base64 encoded front image
     * @param string|null $backImage Base64 encoded back image (optional but recommended)
     * @return array Extracted data from the ID
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

                // Log the raw OCR response for debugging
                Log::info('Nubarium OCR raw response', [
                    'nombres' => $data['nombres'] ?? 'NOT_FOUND',
                    'primerApellido' => $data['primerApellido'] ?? 'NOT_FOUND',
                    'segundoApellido' => $data['segundoApellido'] ?? 'NOT_FOUND',
                    'curp' => $data['curp'] ?? 'NOT_FOUND',
                    'fechaNacimiento' => $data['fechaNacimiento'] ?? 'NOT_FOUND',
                    'all_keys' => array_keys($data),
                ]);

                // Check for error response
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
                        'tipo' => $data['tipo'] ?? null, // INE, IFE
                        'subtipo' => $data['subTipo'] ?? null, // C, D, E, F, G, H
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
     * Endpoint: ine.nubarium.com/ine/v2/valida_ine
     *
     * IMPORTANT: Only send the MANDATORY fields according to credential type:
     * - Type E, F, G, H: cic + identificadorCiudadano (mandatory)
     * - Type D: cic + ocr (mandatory)
     * - Type C: claveElector + numeroEmision + ocr (mandatory)
     *
     * @param array $ineData Data from the INE (cic, identificadorCiudadano, etc.)
     * @return array Validation result
     */
    public function validateIneAgainstList(array $ineData): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        // Determine credential type (subtipo from OCR: C, D, E, F, G, H)
        $subtipo = strtoupper($ineData['subtipo'] ?? '');
        $payload = [];

        Log::info('Nubarium INE validation - building payload', [
            'subtipo' => $subtipo,
            'has_cic' => !empty($ineData['cic']),
            'has_identificador_ciudadano' => !empty($ineData['identificador_ciudadano']),
            'has_ocr' => !empty($ineData['ocr']),
            'has_clave_elector' => !empty($ineData['clave_elector']),
        ]);

        // Build payload based on INE type - send ONLY mandatory fields
        if (in_array($subtipo, ['E', 'F', 'G', 'H'])) {
            // Types E, F, G, H: ONLY cic + identificadorCiudadano
            if (!empty($ineData['cic']) && !empty($ineData['identificador_ciudadano'])) {
                $payload = [
                    'cic' => $ineData['cic'],
                    'identificadorCiudadano' => $ineData['identificador_ciudadano'],
                ];
            }
        } elseif ($subtipo === 'D') {
            // Type D: cic + ocr (identificadorCiudadano is optional but NOT recommended)
            if (!empty($ineData['cic'])) {
                $payload = ['cic' => $ineData['cic']];
                if (!empty($ineData['ocr'])) {
                    $payload['ocr'] = $ineData['ocr'];
                }
            }
        } elseif ($subtipo === 'C') {
            // Type C: claveElector + numeroEmision (ocr optional)
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
            // Unknown type - try best combination available
            // Priority: cic+identificadorCiudadano > cic+ocr > claveElector
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
            Log::warning('Nubarium INE validation - no valid payload could be built', [
                'available_data' => array_keys(array_filter($ineData)),
            ]);
            return [
                'success' => false,
                'error' => 'No se pudieron obtener los datos necesarios del INE para validación. Se requiere CIC/OCR/Clave de Elector.',
            ];
        }

        $this->logRequest('POST', 'ine/v2/valida_ine', $payload);

        try {
            $response = $this->apiCall('ine', 'POST', '/ine/v2/valida_ine', $payload);

            $this->logResponse($response, 'ine/v2/valida_ine');

            if ($response->successful()) {
                $data = $response->json();

                // Check status - "OK" means valid, "ERROR" with codes for different issues
                $status = $data['estatus'] ?? '';
                $isValid = $status === 'OK';
                $messageCode = $data['claveMensaje'] ?? null;

                // Message codes from Nubarium:
                // 1 = No está en la lista nominal (no encontrado)
                // 2 = Solo vigente como identificación (no puede votar)
                // 3 = Vigente y puede votar
                // 4 = Error de datos (campos incorrectos enviados)
                // 5 = Credencial reportada como robada/extraviada

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
     * Full INE validation: Extract data via OCR and validate against nominal list.
     * This is a convenience method that combines both operations.
     *
     * @param string $frontImage Base64 encoded front image
     * @param string|null $backImage Base64 encoded back image
     * @param bool $validateAgainstList Whether to also validate against INE list
     * @return array Combined result
     */
    public function validateIne(string $frontImage, ?string $backImage = null, bool $validateAgainstList = true): array
    {
        // First, extract data using OCR
        $ocrResult = $this->extractIneData($frontImage, $backImage);

        if (!$ocrResult['success']) {
            return $ocrResult;
        }

        $result = [
            'success' => true,
            'ocr_data' => $ocrResult['data'],
            'validation_code' => $ocrResult['validation_code'],
        ];

        // If requested, validate against the INE list
        if ($validateAgainstList && !empty($ocrResult['data'])) {
            $validationResult = $this->validateIneAgainstList($ocrResult['data']);
            $result['list_validation'] = $validationResult;
            $result['is_valid'] = $validationResult['valid'] ?? false;
        }

        return $result;
    }

    /**
     * Get JWT token for Biometric SDK.
     * The token is used by the frontend SDK for face capture and document scanning.
     *
     * @param string $transactionId Unique transaction ID for tracking
     * @return array Token data
     */
    public function getBiometricToken(string $transactionId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Servicio no configurado'];
        }

        // Cache token for 5 minutes to avoid unnecessary API calls
        $cacheKey = "nubarium_token_{$this->tenant->id}_{$transactionId}";

        return Cache::remember($cacheKey, 300, function () use ($transactionId) {
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

    /**
     * Validate SPEI CEP (Comprobante Electrónico de Pago).
     *
     * @param array $data CEP data
     * @return array Validation result
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
     * Check OFAC & UN sanctions block lists.
     * Endpoint: api.nubarium.com/blocklist/v1/query
     *
     * @param string $name Full name to check
     * @param int $similarity Similarity threshold (0-100, default 80)
     * @return array Check result
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

                // Nubarium returns status: "OK" and records array
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

            // If OFAC service is not available (404), return "not found" to allow flow to continue
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
     * Check Mexican PLD (Prevención de Lavado de Dinero) blacklists.
     * Includes PGR, PGJ, PEPs, SAT 69/69B, Interpol, DEA, FBI, CIA, etc.
     * Endpoint: api.nubarium.com/blacklists/v1/consulta
     *
     * @param string $fullName Full name to check (nombreCompleto)
     * @param string|null $curp Optional CURP for better matching
     * @param int $similarity Similarity threshold (0-100, default 80)
     * @return array Check result
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

                // Nubarium returns estatus: "OK" and resultados array
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

            // If blacklists service is not available (404), return "not found" to allow flow to continue
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
     *
     * @param string $curp CURP to lookup
     * @param string $nss NSS (Número de Seguro Social) if available
     * @return array Employment history
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
     * Validate professional license (Cédula Profesional).
     *
     * @param string $cedula Professional license number
     * @return array Validation result
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
     * Validate CURP format.
     */
    private function isValidCurpFormat(string $curp): bool
    {
        // CURP format: 18 alphanumeric characters
        // Pattern: AAAA######HXXNNN##
        // 4 letters + 6 digits + H/M + 2 letters (state) + 3 consonants + 2 alphanumeric
        $pattern = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9][0-9]$/i';

        return strlen($curp) === 18 && preg_match($pattern, $curp);
    }

    /**
     * Validate RFC format.
     */
    private function isValidRfcFormat(string $rfc): bool
    {
        $length = strlen($rfc);

        // RFC for individuals: 13 characters (AAAA######XXX)
        // RFC for companies: 12 characters (AAA######XXX)
        if ($length !== 12 && $length !== 13) {
            return false;
        }

        $pattern = $length === 13
            ? '/^[A-Z]{4}[0-9]{6}[A-Z0-9]{3}$/i' // Persona física
            : '/^[A-Z]{3}[0-9]{6}[A-Z0-9]{3}$/i'; // Persona moral

        return preg_match($pattern, $rfc);
    }

    /**
     * Format date to DD/MM/YYYY format expected by Nubarium.
     */
    private function formatDate(string $date): string
    {
        try {
            $parsed = \Carbon\Carbon::parse($date);

            return $parsed->format('d/m/Y');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Get available services for this provider.
     */
    public function getAvailableServices(): array
    {
        return self::SERVICES;
    }

    /**
     * Get Mexican states for CURP.
     */
    public function getBirthStates(): array
    {
        return self::BIRTH_STATES;
    }
}
