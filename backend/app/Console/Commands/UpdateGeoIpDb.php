<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
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

        // Extraer .mmdb usando el `tar` nativo del sistema.
        //
        // Antes usabamos PharData de PHP pero carga el archivo entero en
        // memoria, lo que truena con memory_limit=128M (el tar.gz pesa ~30MB
        // pero las estructuras internas de Phar inflan ~5-6x). El tar
        // de Linux extrae con streams, sin memoria significativa.
        //
        // MaxMind empaca el .mmdb dentro de un subdirectorio
        // `GeoLite2-City_YYYYMMDD/` asi que extraemos a un staging
        // dir y movemos el .mmdb encontrado.
        $this->info('Extrayendo .mmdb (via tar)...');

        $stagingDir = $targetDir . '/_extract_' . time();
        if (! @mkdir($stagingDir, 0755, true)) {
            $this->error("No se pudo crear staging dir: {$stagingDir}");
            @unlink($tmpTarGz);
            return self::FAILURE;
        }

        try {
            // tar -xzf {archivo} -C {dest} --wildcards '*.mmdb'
            //   -x extract, -z gzip, -f file, -C cambia a dir destino
            //   --wildcards permite glob para extraer solo el .mmdb
            $process = new Process([
                'tar',
                '-xzf', $tmpTarGz,
                '-C', $stagingDir,
                '--wildcards',
                '*.mmdb',
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(
                    'tar exit ' . $process->getExitCode() . ': ' . $process->getErrorOutput()
                );
            }

            // Buscar el .mmdb extraido (esta dentro de algun subdirectorio)
            $extracted = null;
            $rii = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($stagingDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($rii as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.mmdb')) {
                    $extracted = $file->getPathname();
                    break;
                }
            }

            if (! $extracted) {
                throw new \RuntimeException('No se encontro .mmdb en el archivo extraido');
            }

            // Backup del actual si existe (rollback rapido si la nueva esta rota)
            if (is_file($targetFile)) {
                @rename($targetFile, $targetFile . '.bak');
            }

            if (! @rename($extracted, $targetFile)) {
                // rename puede fallar entre filesystems; intentar copy + unlink
                if (! @copy($extracted, $targetFile)) {
                    throw new \RuntimeException("No se pudo mover .mmdb a {$targetFile}");
                }
                @unlink($extracted);
            }
            chmod($targetFile, 0644);
        } catch (Throwable $e) {
            $this->error('Error al extraer: ' . $e->getMessage());
            // Restore desde backup si quedo huerfano
            if (! is_file($targetFile) && is_file($targetFile . '.bak')) {
                @rename($targetFile . '.bak', $targetFile);
            }
            $this->cleanupStaging($stagingDir);
            @unlink($tmpTarGz);
            return self::FAILURE;
        }

        $this->cleanupStaging($stagingDir);
        @unlink($tmpTarGz);

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

    private function cleanupStaging(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        // Borrar recursivamente sin shell_exec (mas seguro)
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
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
