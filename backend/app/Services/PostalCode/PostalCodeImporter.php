<?php

namespace App\Services\PostalCode;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Parseo reutilizable del catálogo de códigos postales de SEPOMEX.
 *
 * Extrae la lógica que antes vivía en el comando `postal-codes:import` para que
 * el comando CLI y el flujo de carga del panel (job → tabla de staging)
 * compartan exactamente el mismo mapeo de columnas y la misma inserción por
 * lotes. Acepta el archivo oficial "Descarga nacional" (TXT delimitado por '|',
 * codificación Windows-1252) o un CSV con encabezados equivalentes. Mapea por
 * NOMBRE de columna del encabezado (tolera variantes de mayúsculas).
 */
class PostalCodeImporter
{
    /**
     * Clave de caché con la marca de última importación. `postal_codes` no tiene
     * timestamps (catálogo estático), así que la vigencia se mide contra esta
     * marca. La lee `postal-codes:check-freshness` para avisar si venció.
     */
    public const LAST_IMPORT_CACHE_KEY = 'postal_codes:last_import';

    /** Tamaño de lote de inserción por defecto. */
    public const DEFAULT_CHUNK_SIZE = 1000;

    /** Cada cuántas filas insertadas se reporta progreso vía el callback. */
    private const PROGRESS_ROW_INTERVAL = 20000;

    /** Cuántas filas de muestra se acumulan para el preview. */
    private const SAMPLE_SIZE = 10;

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

    /** Tamaño de lote de inserción efectivo (configurable vía setChunkSize). */
    private int $chunkSize = self::DEFAULT_CHUNK_SIZE;

    /**
     * Ajusta el tamaño del lote de inserción (mínimo 100). Fluida para permitir
     * `$importer->setChunkSize(...)->importToTable(...)`.
     */
    public function setChunkSize(int $chunkSize): self
    {
        $this->chunkSize = max(100, $chunkSize);

        return $this;
    }

    /**
     * Parsea el archivo SEPOMEX en `$path` e inserta sus filas en `$table`.
     *
     * Reemplaza el contenido de la tabla destino: la valida (encabezado) y luego
     * la trunca antes de insertar, para no dejarla vacía si el archivo no es
     * parseable. Parsea por streaming (fgets) e inserta en lotes. Acumula, sin
     * releer la tabla, los conteos que alimentan preview y validación.
     *
     * @param  string  $path  Ruta al archivo (TXT con '|' o CSV).
     * @param  string  $table  Tabla destino (p. ej. `postal_codes` o `postal_codes_staging`).
     * @param  (callable(int $rowsProcessed): void)|null  $onProgress  Se invoca cada PROGRESS_ROW_INTERVAL filas insertadas.
     *
     * @throws \RuntimeException Si el archivo no existe, no se puede abrir o carece del encabezado esperado.
     */
    public function importToTable(string $path, string $table, ?callable $onProgress = null): PostalCodeImportResult
    {
        if (! is_file($path)) {
            throw new \RuntimeException("No existe el archivo: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el archivo.');
        }

        try {
            // Localiza el encabezado y deduce el delimitador ANTES de truncar:
            // un archivo sin encabezado válido nunca vacía la tabla destino.
            [$delimiter, $indexByColumn] = $this->resolveHeader($handle);
            if ($indexByColumn === null) {
                throw new \RuntimeException('No se encontró el encabezado (se esperaba d_codigo/d_asenta).');
            }

            // Reemplazo total: se descarta el contenido previo de la tabla destino.
            DB::table($table)->truncate();

            $batch = [];
            $imported = 0;
            $skipped = 0;
            $states = [];          // set de estados distintos (clave => true)
            $municipalities = [];  // set de municipios distintos (clave => true)
            $sample = [];          // primeras SAMPLE_SIZE filas mapeadas

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

                if ($row['estado'] !== '') {
                    $states[$row['estado']] = true;
                }
                if ($row['municipio'] !== '') {
                    $municipalities[$row['municipio']] = true;
                }
                if (count($sample) < self::SAMPLE_SIZE) {
                    $sample[] = $row;
                }

                $batch[] = $row;
                if (count($batch) >= $this->chunkSize) {
                    DB::table($table)->insert($batch);
                    $imported += count($batch);
                    $batch = [];
                    if ($onProgress !== null && $imported % self::PROGRESS_ROW_INTERVAL === 0) {
                        $onProgress($imported);
                    }
                }
            }

            if ($batch) {
                DB::table($table)->insert($batch);
                $imported += count($batch);
            }
        } finally {
            fclose($handle);
        }

        return new PostalCodeImportResult(
            rowsCount: $imported,
            statesCount: count($states),
            municipalitiesCount: count($municipalities),
            sample: $sample,
            skipped: $skipped,
        );
    }

    /**
     * Registra la marca de última importación (vigencia del catálogo). Persistente
     * vía cache store; la lee `postal-codes:check-freshness`.
     */
    public function touchFreshness(int $count): void
    {
        Cache::forever(self::LAST_IMPORT_CACHE_KEY, [
            'at' => now()->toIso8601String(),
            'count' => $count,
        ]);
    }

    /**
     * Lee líneas hasta encontrar el encabezado; devuelve [delimitador, mapa
     * nombreColumna=>índice]. Si no lo encuentra, devuelve [',', null].
     *
     * @param  resource  $handle
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
     * @return array<string,string|null>|null
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
