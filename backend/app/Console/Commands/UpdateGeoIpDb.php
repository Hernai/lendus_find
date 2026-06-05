<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PharData;
use Throwable;

/**
 * Descarga la ultima base de datos GeoLite2-City desde el CDN de MaxMind.
 *
 * Setup requerido en .env:
 *   MAXMIND_ACCOUNT_ID=123456
 *   MAXMIND_LICENSE_KEY=xxxxxxxxxxxxxxxx_xxxxxx
 *
 * El License Key se genera en https://www.maxmind.com/en/accounts/current/license-key
 * marcando "Will this key be used for GeoIP Update? YES".
 *
 * Schedule: cada miercoles 03:00 (MaxMind libera nueva version los martes).
 * Configurado en routes/console.php.
 *
 * Uso manual:
 *   php artisan audit-logs:update-geoip-db        # download + extract
 *   php artisan audit-logs:update-geoip-db --check  # solo verifica setup
 */
class UpdateGeoIpDb extends Command
{
    protected $signature = 'audit-logs:update-geoip-db
                            {--check : Solo verifica credenciales y la DB actual, no descarga}';

    protected $description = 'Descarga la ultima GeoLite2-City.mmdb de MaxMind.';

    private const EDITION_ID = 'GeoLite2-City';

    public function handle(): int
    {
        $accountId = config('services.maxmind.account_id') ?: env('MAXMIND_ACCOUNT_ID');
        $licenseKey = config('services.maxmind.license_key') ?: env('MAXMIND_LICENSE_KEY');

        if (! $accountId || ! $licenseKey) {
            $this->error('MAXMIND_ACCOUNT_ID y MAXMIND_LICENSE_KEY son requeridos.');
            $this->line('Genera tu License Key en:');
            $this->line('  https://www.maxmind.com/en/accounts/current/license-key');
            return self::FAILURE;
        }

        $targetDir = storage_path('app/geoip');
        $targetFile = $targetDir . '/' . self::EDITION_ID . '.mmdb';

        if (! is_dir($targetDir)) {
            if (! @mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
                $this->error("No se pudo crear directorio: {$targetDir}");
                return self::FAILURE;
            }
        }

        if ($this->option('check')) {
            return $this->checkOnly($targetFile);
        }

        $url = 'https://download.maxmind.com/app/geoip_download'
            . '?edition_id=' . self::EDITION_ID
            . '&license_key=' . urlencode((string) $licenseKey)
            . '&suffix=tar.gz';

        $tmpTarGz = $targetDir . '/' . self::EDITION_ID . '.tar.gz';
        $this->info('Descargando GeoLite2-City desde MaxMind...');

        try {
            $response = Http::withBasicAuth((string) $accountId, (string) $licenseKey)
                ->timeout(300)
                ->sink($tmpTarGz)
                ->get($url);

            if (! $response->successful()) {
                $this->error("Descarga fallo HTTP {$response->status()}");
                @unlink($tmpTarGz);
                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error('Error de descarga: ' . $e->getMessage());
            @unlink($tmpTarGz);
            return self::FAILURE;
        }

        $sizeMB = round(filesize($tmpTarGz) / 1024 / 1024, 1);
        $this->info("Descargado: {$sizeMB} MB");

        // Extraer .mmdb del tar.gz. MaxMind empaca dentro de
        // GeoLite2-City_YYYYMMDD/GeoLite2-City.mmdb
        $this->info('Extrayendo .mmdb...');
        try {
            $phar = new PharData($tmpTarGz);
            $extracted = null;
            foreach (new \RecursiveIteratorIterator($phar) as $file) {
                if (str_ends_with($file->getFilename(), '.mmdb')) {
                    $extracted = $file;
                    break;
                }
            }

            if (! $extracted) {
                $this->error('No se encontro .mmdb dentro del tar.gz');
                @unlink($tmpTarGz);
                return self::FAILURE;
            }

            // Backup del actual si existe (rollback rapido si la nueva esta rota)
            if (is_file($targetFile)) {
                @rename($targetFile, $targetFile . '.bak');
            }

            copy($extracted->getPathname(), $targetFile);
            chmod($targetFile, 0644);
            @unlink($tmpTarGz);
        } catch (Throwable $e) {
            $this->error('Error al extraer: ' . $e->getMessage());
            @unlink($tmpTarGz);
            // Restore desde backup si quedo huerfano
            if (is_file($targetFile . '.bak')) {
                @rename($targetFile . '.bak', $targetFile);
            }
            return self::FAILURE;
        }

        $finalSizeMB = round(filesize($targetFile) / 1024 / 1024, 1);
        $this->info("OK: {$targetFile} ({$finalSizeMB} MB)");
        Log::info('GeoLite2-City actualizada', [
            'path' => $targetFile,
            'size_mb' => $finalSizeMB,
        ]);

        // Borrar backup si todo salio bien
        @unlink($targetFile . '.bak');

        return self::SUCCESS;
    }

    private function checkOnly(string $targetFile): int
    {
        $this->info('=== Setup MaxMind ===');
        $this->line('Account ID: ' . (config('services.maxmind.account_id') ? 'OK' : 'FALTA'));
        $this->line('License Key: ' . (config('services.maxmind.license_key') ? 'OK' : 'FALTA'));
        $this->line('DB local: ' . (is_file($targetFile)
            ? 'OK (' . round(filesize($targetFile) / 1024 / 1024, 1) . ' MB, mtime ' . date('Y-m-d', filemtime($targetFile)) . ')'
            : 'FALTA'));
        return self::SUCCESS;
    }
}
