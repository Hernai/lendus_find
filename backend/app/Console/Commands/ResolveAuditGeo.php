<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\GeoIp\MaxMindGeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resuelve la geolocalizacion de audit_logs que tienen ip_address pero
 * no tienen latitude/longitude/city.
 *
 * El middleware LogClientRequest persiste solo ip_address (para no
 * bloquear al cliente). Este command corre offline (schedule horario)
 * y enriquece via MaxMind local — sin HTTP externo, sin rate limit.
 *
 * Uso:
 *   php artisan audit-logs:resolve-geo                    # default 1000 IPs unicas
 *   php artisan audit-logs:resolve-geo --limit=5000       # batch mas grande
 *   php artisan audit-logs:resolve-geo --since="2 hours"  # solo recientes
 *   php artisan audit-logs:resolve-geo --dry-run          # no actualiza
 */
class ResolveAuditGeo extends Command
{
    protected $signature = 'audit-logs:resolve-geo
                            {--limit=1000 : Max IPs unicas por corrida}
                            {--since= : Solo registros desde (ej. "2 hours", "1 day")}
                            {--dry-run : Reporta pero no actualiza}';

    protected $description = 'Resuelve lat/lng/city de audit_logs con solo ip_address via MaxMind local.';

    public function handle(MaxMindGeoService $geo): int
    {
        if (! $geo->isAvailable()) {
            $this->error('MaxMind GeoLite2-City.mmdb no esta disponible.');
            $this->line('Corre primero: php artisan audit-logs:update-geoip-db');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $since = $this->option('since');
        $dryRun = (bool) $this->option('dry-run');

        $query = AuditLog::query()
            ->whereNull('latitude')
            ->whereNotNull('ip_address');

        if ($since) {
            try {
                $query->where('created_at', '>=', now()->sub($since));
            } catch (Throwable $e) {
                $this->error("--since invalido: '{$since}'. Usa '2 hours' o '1 day'.");
                return self::FAILURE;
            }
        }

        // IPs unicas primero — si la misma IP aparece 100 veces, solo
        // hacemos 1 lookup y actualizamos los 100 registros en un UPDATE.
        $ips = (clone $query)
            ->select('ip_address')
            ->distinct()
            ->limit($limit)
            ->pluck('ip_address');

        if ($ips->isEmpty()) {
            $this->info('Sin audit_logs pendientes de geolocalizar.');
            return self::SUCCESS;
        }

        $this->info("IPs unicas a procesar: {$ips->count()}" . ($dryRun ? ' (DRY-RUN)' : ''));

        $resolved = 0;
        $notFound = 0;
        $rowsUpdated = 0;

        $bar = $this->output->createProgressBar($ips->count());
        $bar->start();

        foreach ($ips as $ip) {
            $loc = $geo->lookup($ip);

            if ($loc === null) {
                $notFound++;
                $bar->advance();
                continue;
            }

            $resolved++;

            if ($dryRun) {
                $bar->advance();
                continue;
            }

            try {
                $count = AuditLog::where('ip_address', $ip)
                    ->whereNull('latitude')
                    ->update([
                        'latitude' => $loc['latitude'] ?? null,
                        'longitude' => $loc['longitude'] ?? null,
                        'city' => $loc['city'] ?? null,
                        'region' => $loc['region'] ?? null,
                        'country' => $loc['country'] ?? null,
                    ]);
                $rowsUpdated += $count;
            } catch (Throwable $e) {
                Log::warning('audit-logs:resolve-geo UPDATE fallo', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("IPs resueltas: {$resolved}");
        $this->info("IPs no encontradas en DB (o privadas): {$notFound}");
        $this->info("Registros actualizados: {$rowsUpdated}");

        return self::SUCCESS;
    }
}
