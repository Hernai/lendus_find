<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\MetadataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persiste un audit_log entry de forma asincrona.
 *
 * Antes el INSERT a audit_logs se hacia en LogClientRequest::terminating().
 * En Apache+EasyApache 4 con mod_proxy_fcgi, fastcgi_finish_request() NO
 * cierra la conexion al cliente — Apache espera al worker antes de liberar
 * el cliente. Resultado: el cliente esperaba ~1.5s por el INSERT en CADA
 * request autenticado.
 *
 * Solucion: dispatch a la queue de Redis. El job tarda <1ms en encolarse
 * y el response al cliente sale inmediato. El queue worker (systemd
 * service `lendusfind-queue`) lo procesa en background, escribiendo a
 * audit_logs sin afectar al usuario.
 */
class PersistAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array $payload Datos pre-empaquetados por LogClientRequest::buildPayload()
     */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $p = $this->payload;
        $clientGeo = $p['client_geo'] ?? null;

        $latitude = $clientGeo['latitude'] ?? null;
        $longitude = $clientGeo['longitude'] ?? null;
        $city = null;
        $region = null;
        $country = null;
        $geoSource = $clientGeo ? 'device' : 'ip';

        // IP geo lookup opcional via env. Ahora que el job corre async,
        // el costo no afecta al cliente — pero igual mantenemos el flag
        // por consistencia con el comportamiento previo del middleware.
        if (! $clientGeo && ! empty($p['ip_address']) && env('AUDIT_GEO_LOOKUP_SYNC', false)) {
            try {
                $ipGeo = app(MetadataService::class)->resolveIpGeolocation($p['ip_address']);
                if (is_array($ipGeo)) {
                    $latitude = $latitude ?? ($ipGeo['latitude'] ?? null);
                    $longitude = $longitude ?? ($ipGeo['longitude'] ?? null);
                    $city = $ipGeo['city'] ?? null;
                    $region = $ipGeo['region'] ?? null;
                    $country = $ipGeo['country'] ?? null;
                }
            } catch (Throwable $e) {
                Log::debug('IP geo lookup skipped in audit job', ['error' => $e->getMessage()]);
            }
        }

        try {
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
        } catch (Throwable $e) {
            // No reintentar — si el INSERT falla por datos malos, mejor
            // perder un audit que sumar 3 retries al queue worker.
            Log::warning('PersistAuditLogJob failed', [
                'error' => $e->getMessage(),
                'path' => $p['path'] ?? null,
                'method' => $p['method'] ?? null,
            ]);
        }
    }

    public function backoff(): array
    {
        return [5];
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(2);
    }
}
