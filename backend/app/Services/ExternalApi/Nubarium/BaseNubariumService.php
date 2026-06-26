<?php

namespace App\Services\ExternalApi\Nubarium;

use App\Models\Tenant;
use App\Services\ExternalApi\BaseExternalApiService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base class for all Nubarium services.
 *
 * Provides common functionality:
 * - JWT token authentication
 * - Token caching and refresh
 * - Service URL management
 * - Error handling
 */
abstract class BaseNubariumService extends BaseExternalApiService
{
    protected string $provider = 'nubarium';
    protected string $serviceType = 'kyc';

    /**
     * Nubarium API base URLs by service subdomain.
     */
    protected array $serviceUrls = [
        'auth' => 'https://api.nubarium.com',
        'curp' => 'https://curp.nubarium.com',
        'ine' => 'https://ine.nubarium.com',
        'ocr' => 'https://ocr.nubarium.com',
        'sat' => 'https://sat.nubarium.com',
        'global' => 'https://api.nubarium.com',
        'sdk' => 'https://api.sdk.nubarium.com',
    ];

    /**
     * JWT token cache duration in seconds.
     */
    protected int $tokenCacheDuration = 3500; // ~58 minutes

    /**
     * Track if we've already tried to refresh the token in this request.
     */
    protected bool $tokenRefreshAttempted = false;

    public function __construct(Tenant $tenant)
    {
        parent::__construct($tenant);
    }

    /**
     * Carga la config del tenant para este service_type; si no existe, cae a
     * CUALQUIER integración Nubarium activa.
     *
     * Razón: Nubarium usa UNA sola credencial (api_key/api_secret) para todos
     * sus servicios (KYC/identidad, validación bancaria, OTP…). Así, tener
     * cualquier integración Nubarium activa basta para autenticar — p. ej. la
     * validación de CLABE (service_type 'bank_validation') funciona aunque el
     * tenant solo tenga configurado Nubarium con otro service_type.
     */
    protected function loadConfig(): void
    {
        parent::loadConfig();

        if ($this->config === null) {
            $this->config = \App\Models\TenantApiConfig::where('tenant_id', $this->tenant->id)
                ->where('provider', $this->provider)
                ->where('is_active', true)
                ->first();
        }
    }

    /**
     * Get the username for Basic Auth.
     */
    protected function getUsername(): string
    {
        return $this->config?->api_key ?? '';
    }

    /**
     * Get the password for Basic Auth.
     */
    protected function getPassword(): string
    {
        return $this->config?->api_secret ?? '';
    }

    /**
     * Get or generate JWT token using Basic Auth.
     */
    protected function getJwtToken(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $cacheKey = $this->getTokenCacheKey();

        return Cache::remember($cacheKey, $this->tokenCacheDuration, function () {
            return $this->generateJwtToken();
        });
    }

    /**
     * Get the cache key for JWT token.
     */
    protected function getTokenCacheKey(): string
    {
        return "nubarium_jwt_{$this->tenant->id}";
    }

    /**
     * Force refresh JWT token.
     */
    public function refreshToken(): ?string
    {
        $this->clearTokenCache();
        $this->tokenRefreshAttempted = true;
        return $this->getJwtToken();
    }

    /**
     * Handle 401/403 by refreshing token and retrying once.
     */
    protected function handleUnauthorized(): ?string
    {
        if ($this->tokenRefreshAttempted) {
            Log::warning('Nubarium: Token refresh already attempted, not retrying', [
                'tenant_id' => $this->tenant->id,
            ]);
            return null;
        }

        Log::info('Nubarium: Attempting token refresh due to expired token', [
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

        $baseUrl = $this->serviceUrls['auth'];
        $tokenUrl = "{$baseUrl}/global/account/v1/generate-jwt";

        Log::info('Nubarium: Attempting JWT generation', [
            'tenant_id' => $this->tenant->id,
            'url' => $tokenUrl,
        ]);

        try {
            $response = Http::withBasicAuth($username, $password)
                ->timeout(30)
                ->post($tokenUrl, ['expire' => 60]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['bearer_token'] ?? $data['token'] ?? $data['access_token'] ?? null;

                if ($token) {
                    Log::info('Nubarium: JWT token generated successfully', [
                        'tenant_id' => $this->tenant->id,
                    ]);
                    return $token;
                }

                if (isset($data['status']) && $data['status'] === 'ERROR') {
                    Log::error('Nubarium: API returned error', [
                        'error' => $data['error'] ?? 'Unknown error',
                    ]);
                    return null;
                }

                Log::error('Nubarium: JWT response missing token', ['response' => $data]);
                return null;
            }

            Log::error('Nubarium: JWT generation failed', [
                'status' => $response->status(),
                // Trunco para no loguear tokens/credenciales completas si la
                // respuesta de error las refleja.
                'body' => substr($response->body(), 0, 500),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Nubarium: JWT generation exception', [
                'error' => self::sanitizeError($e),
            ]);
            return null;
        }
    }

    /**
     * Clear cached JWT token.
     */
    public function clearTokenCache(): void
    {
        Cache::forget($this->getTokenCacheKey());
    }

    /**
     * Sanitiza el mensaje de un Throwable antes de loguearlo.
     *
     * Why: Las excepciones de Guzzle/Http en endpoints biometricos (INE,
     * liveness, face-match) muestran el request body completo en el mensaje,
     * incluyendo imagenes base64 de ~1MB y, en el path de auth, las
     * credenciales del tenant. Loguear eso satura storage/logs e introduce
     * datos sensibles en archivos planos.
     *
     * How: Truncamos a 500 chars y reemplazamos cualquier bloque base64
     * largo (>=120 chars) por un placeholder. Tambien filtramos campos
     * conocidos (password, token, bearer) por si aparecen en el mensaje.
     */
    public static function sanitizeError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        // Reemplaza bloques base64 largos por placeholder.
        $msg = preg_replace('/[A-Za-z0-9+\/=]{120,}/', '<base64:redacted>', $msg) ?? $msg;

        // Censura credenciales o tokens si quedaran en el mensaje.
        $msg = preg_replace('/("?(?:password|bearer_token|access_token|token|authorization)"?\s*[:=]\s*"?)[^",\s}]+/i', '$1<redacted>', $msg) ?? $msg;

        if (strlen($msg) > 500) {
            $msg = substr($msg, 0, 500) . '...<truncated>';
        }

        return $msg;
    }

    /**
     * Get default headers with Bearer token.
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
     */
    protected function getServiceUrl(string $service): string
    {
        return $this->serviceUrls[$service] ?? $this->serviceUrls['global'];
    }

    /**
     * Make an HTTP request to a specific service.
     */
    protected function serviceHttp(string $service): \Illuminate\Http\Client\PendingRequest
    {
        // asJson() garantiza que el body se serialice como JSON (todos los
        // endpoints de Nubarium esperan JSON). Sin esto, un default de form-encode
        // haría que el endpoint no encuentre los campos (p.ej. "message missing").
        return Http::baseUrl($this->getServiceUrl($service))
            ->asJson()
            ->timeout($this->timeout)
            ->withHeaders($this->getDefaultHeaders());
    }

    /**
     * Make an API call with automatic token refresh on 401/403.
     */
    protected function apiCall(
        string $service,
        string $method,
        string $endpoint,
        array $payload = [],
        ?int $timeout = null
    ): Response {
        $http = $this->serviceHttp($service);

        if ($timeout) {
            $http = $http->timeout($timeout);
        }

        $method = strtoupper($method);
        $response = $method === 'GET'
            ? $http->get($endpoint, $payload)
            : $http->post($endpoint, $payload);

        // Handle expired token
        if (($response->status() === 401 || $response->status() === 403) && !$this->tokenRefreshAttempted) {
            Log::info('Nubarium: Got ' . $response->status() . ', attempting token refresh', [
                'endpoint' => $endpoint,
                'tenant_id' => $this->tenant->id,
            ]);

            $newToken = $this->handleUnauthorized();

            if ($newToken) {
                $http = $this->serviceHttp($service);
                if ($timeout) {
                    $http = $http->timeout($timeout);
                }

                $response = $method === 'GET'
                    ? $http->get($endpoint, $payload)
                    : $http->post($endpoint, $payload);
            }
        }

        return $response;
    }

    /**
     * Test API connection.
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
            $this->clearTokenCache();
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
                'error' => 'Verifique las credenciales',
            ];
        } catch (\Exception $e) {
            $sanitized = self::sanitizeError($e);
            $this->updateTestResult(false, $sanitized);
            return [
                'success' => false,
                'message' => 'Error de conexión',
                'error' => $sanitized,
            ];
        }
    }
}
