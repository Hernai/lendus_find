<?php

namespace App\Http\Middleware;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\MetadataService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Loguea cada request del cliente (apps móviles, PWA, web) en `audit_logs`
 * con la acción, entidad y metadata, incluyendo la geolocalización enviada
 * por el dispositivo en los headers `X-Geo-Lat`, `X-Geo-Lng`, `X-Geo-Accuracy`.
 *
 * Se debe registrar DESPUÉS de `tenant` + `auth:sanctum` para que tengamos
 * tenant y usuario disponibles. El registro es post-respuesta para capturar
 * status_code y duración.
 *
 * Solo se loguean rutas bajo `api/v2/*` para no inundar la tabla con assets
 * o endpoints públicos triviales como /health.
 */
class LogClientRequest
{
    /** Endpoints que NO se loguean (alto volumen, poco valor). */
    private const SKIP_PATHS = [
        'api/v2/public/health',
        'api/v2/public/version',
        'api/v2/public/manifest',
    ];

    /**
     * Sufijos de path (al final) que NO se loguean — evita meta-ruido:
     * ver los audit-logs no debería generar más audit logs.
     */
    private const SKIP_SUFFIXES = [
        '/audit-logs',
        '/api-logs',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if ($this->shouldSkip($request)) {
            return $response;
        }

        try {
            $this->log($request, $response, $startedAt);
        } catch (Throwable $e) {
            // No bloquear nunca la respuesta por errores de logging.
            Log::warning('LogClientRequest failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    /**
     * Política de retención (estrategia B):
     *
     * - Skip explícito: health, version, manifest (público trivial).
     * - Se loguea TODO POST/PUT/PATCH/DELETE de cualquier rol (mutaciones).
     * - Se loguea TODO GET hecho por staff (auditoría de accesos a
     *   expedientes y documentos — relevante para compliance CNBV/PLD).
     * - Skip GETs de un ApplicantAccount: el aplicante consultando su propio
     *   expediente no es evento auditable, solo añade ruido.
     */
    private function shouldSkip(Request $request): bool
    {
        $path = $request->path();
        foreach (self::SKIP_PATHS as $skip) {
            if (str_starts_with($path, $skip)) return true;
        }
        foreach (self::SKIP_SUFFIXES as $suffix) {
            if (str_ends_with($path, $suffix)) return true;
        }

        $method = strtoupper($request->method());
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false; // mutaciones siempre se loguean
        }

        // GET / HEAD / OPTIONS — solo si el usuario es staff.
        $user = $request->user();
        if ($user instanceof \App\Models\StaffAccount) {
            return false;
        }
        return true;
    }

    private function log(Request $request, Response $response, float $startedAt): void
    {
        /** @var MetadataService $meta */
        $meta = app(MetadataService::class);
        $captured = $meta->capture($request);
        $clientGeo = $meta->parseClientGeo($request);

        // El usuario puede venir de Sanctum (requests autenticados) o ser
        // resuelto manualmente por el controller (login/verifyOtp setea
        // $request->attributes->'audit_user'). Esto permite correlacionar
        // el HTTP_REQUEST del propio login con el applicant/staff dueño.
        $user = $request->user() ?? $request->attributes->get('audit_user');
        $tenantId = $captured['tenant_id'] ?? null;

        $applicantId = null;
        $userId = null;
        if ($user) {
            if (is_a($user, \App\Models\ApplicantAccount::class)) {
                $applicantId = (string) $user->id;
            } elseif (is_a($user, \App\Models\StaffAccount::class)) {
                $userId = (string) $user->id;
            }
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'applicant_id' => $applicantId,
            'application_id' => $request->route('id') && $this->isApplicationRoute($request)
                ? $request->route('id')
                : null,
            'action' => AuditAction::HTTP_REQUEST->value,
            'entity_type' => 'http_request',
            'entity_id' => null,
            'metadata' => [
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'query' => $request->query() ?: null,
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'platform' => $captured['platform'] ?? null,
                'app_version' => $captured['app_version'] ?? null,
                'device_id' => $captured['device_id'] ?? null,
                'geo_source' => $clientGeo ? 'device' : 'ip',
                'geo_accuracy_m' => $clientGeo['accuracy'] ?? null,
            ],
            'ip_address' => $captured['ip_address'] ?? null,
            'user_agent' => $captured['user_agent'] ?? null,
            'latitude' => $clientGeo['latitude'] ?? ($captured['geolocation']['latitude'] ?? null),
            'longitude' => $clientGeo['longitude'] ?? ($captured['geolocation']['longitude'] ?? null),
            'city' => $captured['geolocation']['city'] ?? null,
            'region' => $captured['geolocation']['region'] ?? null,
            'country' => $captured['geolocation']['country'] ?? null,
            'device_type' => $captured['device_info']['device_type'] ?? null,
            'browser' => $captured['device_info']['browser'] ?? null,
            'browser_version' => $captured['device_info']['browser_version'] ?? null,
            'os' => $captured['device_info']['os'] ?? null,
            'os_version' => $captured['device_info']['os_version'] ?? null,
            'created_at' => now(),
        ]);
    }

    private function isApplicationRoute(Request $request): bool
    {
        return str_contains($request->path(), '/applications/');
    }
}
