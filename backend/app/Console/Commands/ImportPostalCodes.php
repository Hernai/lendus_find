<?php

namespace App\Console\Commands;

use App\Services\PostalCode\PostalCodeImporter;
use Illuminate\Console\Command;

/**
 * Importa el catálogo de códigos postales de SEPOMEX a la tabla postal_codes.
 *
 * Thin wrapper: delega el parseo/mapeo/inserción en
 * {@see \App\Services\PostalCode\PostalCodeImporter}, el mismo servicio que usa
 * la carga desde el panel. Acepta el archivo oficial "Descarga nacional" (TXT
 * delimitado por '|', codificación Windows-1252) o un CSV con encabezados
 * equivalentes.
 *
 *   php artisan postal-codes:import storage/app/CPdescarga.txt --truncate
 *
 * NOTA: el servicio SIEMPRE reemplaza el contenido de la tabla destino (la
 * trunca antes de insertar), así que la importación ahora reemplaza el catálogo
 * aunque no se pase `--truncate`. La opción se conserva por compatibilidad y
 * solo controla el mensaje informativo de vaciado.
 *
 * Fuente oficial (requiere registro gratis):
 *   https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx
 */
class ImportPostalCodes extends Command
{
    protected $signature = 'postal-codes:import
        {file : Ruta al archivo SEPOMEX (TXT con | o CSV)}
        {--truncate : (Compat) La tabla se reemplaza siempre; solo informa el vaciado}
        {--chunk=1000 : Tamaño del lote de inserción}';

    protected $description = 'Importa el catálogo de códigos postales (SEPOMEX) a postal_codes.';

    public function handle(PostalCodeImporter $importer): int
    {
        $path = $this->argument('file');
        $importer->setChunkSize((int) $this->option('chunk'));

        // El servicio siempre trunca la tabla destino; el flag solo conserva el
        // mensaje observable histórico.
        if ($this->option('truncate')) {
            $this->info('Tabla postal_codes vaciada.');
        }

        try {
            $result = $importer->importToTable(
                $path,
                'postal_codes',
                function (int $imported): void {
                    $this->line("  … {$imported} importados");
                }
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Marca de última importación (mide vigencia; postal_codes no tiene timestamps).
        $importer->touchFreshness($result->rowsCount);

        $this->newLine();
        $this->info("Importados: {$result->rowsCount}. Omitidos (CP inválido): {$result->skipped}.");

        return self::SUCCESS;
    }
}
