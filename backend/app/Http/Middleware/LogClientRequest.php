<?php

namespace App\Http\Middleware;

use App\Jobs\PersistAuditLogJob;
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
 * tenant y usuario disponibles.
 *
 * El INSERT a `audit_logs` (y, si hace falta, el IP geo lookup) corren
 * DESPUÉS del response al cliente vía `app()->terminating()`. Así no
 * agregan latencia al request. En Apache + PHP-FPM, `terminating()` se
 * dispara después de que el response llega al cliente, antes del shutdown
 * del worker.
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

        // Capturamos los datos del request AHORA y despachamos el INSERT a
        // `audit_logs` como queue job. El response sale al cliente sin esperar
        // la escritura.
        //
        // Antes probamos `app()->terminating()` con fastcgi_finish_request(),
        // pero en Apache+EasyApache4 con mod_proxy_fcgi el cliente seguia
        // esperando ~280ms-1.5s al INSERT porque Apache no liberaba la
        // conexion. Queue dispatch a Redis es ~1ms y desacopla totalmente:
        // el queue worker (systemd `lendusfind-queue`) procesa en background.
        $payload = $this->buildPayload($request, $response, $startedAt);

        try {
            PersistAuditLogJob::dispatch($payload);
        } catch (Throwable $e) {
            // Si Redis esta caido el dispatch falla. Logueamos pero no rompemos
            // el response. Audit log se pierde para ese request pero la app
            // sigue sirviendo.
            Log::warning('Failed to dispatch PersistAuditLogJob', ['error' => $e->getMessage()]);
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

    /**
     * Empaca los datos del request en un array que se pueda persistir más
     * tarde sin necesitar el Request original (que puede haberse destruido).
     */
    private function buildPayload(Request $request, Response $response, float $startedAt): array
    {
        /** @var MetadataService $meta */
        $meta = app(MetadataService::class);
        $captured = $meta->capture($request);
        $clientGeo = $meta->parseClientGeo($request);

        $user = $request->user() ?? $request->attributes->get('audit_user');
        $applicantId = null;
        $userId = null;
        if ($user) {
            if (is_a($user, \App\Models\ApplicantAccount::class)) {
                $applicantId = (string) $user->id;
            } elseif (is_a($user, \App\Models\StaffAccount::class)) {
                $userId = (string) $user->id;
            }
        }

        $routeId = $request->route('id');
        $applicationId = ($routeId && $this->isApplicationRoute($request)) ? $routeId : null;

        return [
            'tenant_id' => $captured['tenant_id'] ?? null,
            'user_id' => $userId,
            'applicant_id' => $applicantId,
            'application_id' => $applicationId,
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'query' => $request->query() ?: null,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip_address' => $captured['ip_address'] ?? null,
            'user_agent' => $captured['user_agent'] ?? null,
            'platform' => $captured['platform'] ?? null,
            'app_version' => $captured['app_version'] ?? null,
            'device_id' => $captured['device_id'] ?? null,
            'device_info' => $captured['device_info'] ?? [],
            // Geo del dispositivo (más preciso); si no vino, el lookup por
            // IP se resuelve en persist() — fuera del path crítico.
            'client_geo' => $clientGeo,
        ];
    }

    private function isApplicationRoute(Request $request): bool
    {
        return str_contains($request->path(), '/applications/');
    }
}
