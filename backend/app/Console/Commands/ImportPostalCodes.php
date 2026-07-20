<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Importa el catálogo de códigos postales de SEPOMEX a la tabla postal_codes.
 *
 * Acepta el archivo oficial "Descarga nacional" (TXT delimitado por '|',
 * codificación Windows-1252) o un CSV con encabezados equivalentes. Mapea por
 * NOMBRE de columna del encabezado (tolera variantes de mayúsculas).
 *
 *   php artisan postal-codes:import storage/app/CPdescarga.txt --truncate
 *
 * Fuente oficial (requiere registro gratis):
 *   https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx
 */
class ImportPostalCodes extends Command
{
    protected $signature = 'postal-codes:import
        {file : Ruta al archivo SEPOMEX (TXT con | o CSV)}
        {--truncate : Vacía la tabla antes de importar}
        {--chunk=1000 : Tamaño del lote de inserción}';

    protected $description = 'Importa el catálogo de códigos postales (SEPOMEX) a postal_codes.';

    /**
     * Clave de caché con la marca de última importación. `postal_codes` no tiene
     * timestamps (catálogo estático), así que la vigencia se mide contra esta
     * marca. La lee `postal-codes:check-freshness` para avisar si venció.
     */
    public const LAST_IMPORT_CACHE_KEY = 'postal_codes:last_import';

    /** Mapa columna destino => posibles nombres de encabezado (lowercase). */
    private const COLUMN_MAP = [
        'cp' => ['d_codigo'],
        'asentamiento' => ['d_asenta'],
        'tipo_asentamiento' => ['d_tipo_asenta'],
        'municipio' => ['d_mnpio'],
        'estado' => ['d_estado'],
        'ciudad' => ['d_ciudad'],
        'estado_clave' => ['c_estado'],
        'municipio_clave' => ['c_mnpio'],
    ];

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error('No se pudo abrir el archivo.');

            return self::FAILURE;
        }

        // Localiza el encabezado y deduce el delimitador.
        [$delimiter, $indexByColumn] = $this->resolveHeader($handle);
        if ($indexByColumn === null) {
            fclose($handle);
            $this->error('No se encontró el encabezado (se esperaba d_codigo/d_asenta).');

            return self::FAILURE;
        }

        if ($this->option('truncate')) {
            DB::table('postal_codes')->truncate();
            $this->info('Tabla postal_codes vaciada.');
        }

        $chunkSize = max(100, (int) $this->option('chunk'));
        $batch = [];
        $imported = 0;
        $skipped = 0;

        while (($line = fgets($handle)) !== false) {
            $line = $this->toUtf8(rtrim($line, "\r\n"));
            if ($line === '') {
                continue;
            }

            $cols = str_getcsv($line, $delimiter);
            $row = $this->mapRow($cols, $indexByColumn);

            if ($row === null) {
                $skipped++;
                continue;
            }

            $batch[] = $row;
            if (count($batch) >= $chunkSize) {
                DB::table('postal_codes')->insert($batch);
                $imported += count($batch);
                $batch = [];
                if ($imported % 20000 === 0) {
                    $this->line("  … {$imported} importados");
                }
            }
        }

        if ($batch) {
            DB::table('postal_codes')->insert($batch);
            $imported += count($batch);
        }

        fclose($handle);

        // Marca de última importación (para medir vigencia del catálogo, ya que
        // postal_codes no tiene timestamps). Persistente vía cache store.
        Cache::forever(self::LAST_IMPORT_CACHE_KEY, [
            'at' => now()->toIso8601String(),
            'count' => $imported,
        ]);

        $this->newLine();
        $this->info("Importados: {$imported}. Omitidos (CP inválido): {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * Lee líneas hasta encontrar el encabezado; devuelve [delimitador, mapa
     * nombreColumna=>índice]. Si no lo encuentra, devuelve [',', null].
     *
     * @return array{0: string, 1: array<string,int>|null}
     */
    private function resolveHeader($handle): array
    {
        // Hasta 5 líneas iniciales pueden ser notas/cabeceras del export.
        for ($i = 0; $i < 6; $i++) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }
            $line = $this->toUtf8(rtrim($line, "\r\n"));
            if (stripos($line, 'd_codigo') === false && stripos($line, 'd_asenta') === false) {
                continue;
            }

            $delimiter = substr_count($line, '|') >= substr_count($line, ',') ? '|' : ',';
            $headers = array_map(
                fn ($h) => strtolower(trim($h)),
                str_getcsv($line, $delimiter)
            );

            $index = [];
            foreach (self::COLUMN_MAP as $target => $aliases) {
                foreach ($aliases as $alias) {
                    $pos = array_search($alias, $headers, true);
                    if ($pos !== false) {
                        $index[$target] = $pos;
                        break;
                    }
                }
            }

            return [$delimiter, isset($index['cp'], $index['asentamiento']) ? $index : null];
        }

        return [',', null];
    }

    /**
     * @param  array<int,string>  $cols
     * @param  array<string,int>  $index
     * @return array<string,string>|null
     */
    private function mapRow(array $cols, array $index): ?array
    {
        $cp = preg_replace('/\D/', '', $cols[$index['cp']] ?? '');
        if (strlen($cp) !== 5) {
            return null;
        }

        $get = fn (string $k, int $max) => isset($index[$k], $cols[$index[$k]])
            ? mb_substr(trim($cols[$index[$k]]), 0, $max)
            : null;

        return [
            'cp' => $cp,
            'asentamiento' => $get('asentamiento', 150) ?? '',
            'tipo_asentamiento' => $get('tipo_asentamiento', 60),
            'municipio' => $get('municipio', 150) ?? '',
            'estado' => $get('estado', 100) ?? '',
            'ciudad' => $get('ciudad', 150),
            'estado_clave' => $get('estado_clave', 5),
            'municipio_clave' => $get('municipio_clave', 5),
        ];
    }

    /** Convierte de Windows-1252/latin1 a UTF-8 si la línea no es UTF-8 válido. */
    private function toUtf8(string $line): string
    {
        if ($line === '' || mb_check_encoding($line, 'UTF-8')) {
            return $line;
        }

        return mb_convert_encoding($line, 'UTF-8', 'Windows-1252');
    }
}
