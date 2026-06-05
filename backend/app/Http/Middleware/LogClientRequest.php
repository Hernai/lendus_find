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

        // Capturamos todos los datos necesarios AHORA (mientras request/user
        // están vivos), pero diferimos el INSERT a `audit_logs` y el IP geo
        // lookup hasta DESPUÉS de que el response llegue al cliente.
        $payload = $this->buildPayload($request, $response, $startedAt);

        app()->terminating(function () use ($payload) {
            // CRITICO: forzar fastcgi_finish_request ANTES de hacer trabajo
            // sincrono. En Apache+EasyApache4 con mod_proxy_fcgi, Laravel no
            // siempre lo dispara automaticamente, asi que el cliente acababa
            // esperando ~280ms del INSERT a audit_logs. Llamandolo explicito
            // aqui garantizamos que el response ya esta enviado al cliente
            // antes de tocar la DB.
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            try {
                $this->persist($payload);
            } catch (Throwable $e) {
                Log::warning('LogClientRequest failed', ['error' => $e->getMessage()]);
            }
        });

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

    /**
     * Persistir el audit log. Corre DESPUÉS del response vía
     * `app()->terminating()`. Si la geo del dispositivo no llegó en headers,
     * acá hacemos el lookup por IP (puede tomar 200-500ms pero ya no afecta
     * al cliente — el response ya salió).
     */
    private function persist(array $p): void
    {
        $clientGeo = $p['client_geo'];

        $latitude = $clientGeo['latitude'] ?? null;
        $longitude = $clientGeo['longitude'] ?? null;
        $city = null;
        $region = null;
        $country = null;
        $geoSource = $clientGeo ? 'device' : 'ip';

        // IP geo lookup deshabilitado en path de respuesta. fastcgi_finish_request
        // no esta cerrando la conexion antes del terminating en este stack
        // (Apache + cPanel/EasyApache 4), asi que el cliente esperaba ~500ms del
        // HTTP call a ip-api.com en CADA request.
        //
        // El IP que igualmente quedo persistido (campo ip_address) permite hacer
        // el geo lookup en batch nocturno con un command: para cada audit_log
        // sin lat/lng, resolver y poblar. Eso saca el costo del path critico.
        //
        // TODO: agendar `audit-logs:resolve-geo` command nocturno.
        if (! $clientGeo && ! empty($p['ip_address']) && env('AUDIT_GEO_LOOKUP_SYNC', false)) {
            $ipGeo = app(MetadataService::class)->resolveIpGeolocation($p['ip_address']);
            if (is_array($ipGeo)) {
                $latitude = $latitude ?? ($ipGeo['latitude'] ?? null);
                $longitude = $longitude ?? ($ipGeo['longitude'] ?? null);
                $city = $ipGeo['city'] ?? null;
                $region = $ipGeo['region'] ?? null;
                $country = $ipGeo['country'] ?? null;
            }
        }

        AuditLog::create([
            'tenant_id' => $p['tenant_id'],
            'user_id' => $p['user_id'],
            'applicant_id' => $p['applicant_id'],
            'application_id' => $p['application_id'],
            'action' => AuditAction::HTTP_REQUEST->value,
            'entity_type' => 'http_request',
            'entity_id' => null,
            'metadata' => [
                'method' => $p['method'],
                'path' => $p['path'],
                'query' => $p['query'],
                'status_code' => $p['status_code'],
                'duration_ms' => $p['duration_ms'],
                'platform' => $p['platform'],
                'app_version' => $p['app_version'],
                'device_id' => $p['device_id'],
                'geo_source' => $geoSource,
                'geo_accuracy_m' => $clientGeo['accuracy'] ?? null,
            ],
            'ip_address' => $p['ip_address'],
            'user_agent' => $p['user_agent'],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'city' => $city,
            'region' => $region,
            'country' => $country,
            'device_type' => $p['device_info']['device_type'] ?? null,
            'browser' => $p['device_info']['browser'] ?? null,
            'browser_version' => $p['device_info']['browser_version'] ?? null,
            'os' => $p['device_info']['os'] ?? null,
            'os_version' => $p['device_info']['os_version'] ?? null,
            'created_at' => now(),
        ]);
    }

    private function isApplicationRoute(Request $request): bool
    {
        return str_contains($request->path(), '/applications/');
    }
}
