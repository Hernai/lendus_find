<?php

namespace App\Jobs;

use App\Enums\PostalCodeImportStatus;
use App\Events\PostalCodeImportProgress;
use App\Models\PostalCodeImport;
use App\Services\PostalCode\PostalCodeCatalogSwapper;
use App\Services\PostalCode\PostalCodeFileResolver;
use App\Services\PostalCode\PostalCodeImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Parsea en background el archivo subido del catálogo de códigos postales a la
 * tabla de staging, valida el conteo mínimo y deja el import en `PARSED`
 * (listo para aplicar) o `REJECTED` (con motivo).
 *
 * No aplica el swap: eso ocurre síncrono en `POST .../imports/{id}/apply` tras
 * la confirmación explícita del SUPER_ADMIN (el RENAME es de milisegundos). El
 * progreso se emite por Reverb vía {@see PostalCodeImportProgress}. Los archivos
 * temporales (subido y extraído del ZIP) se borran al terminar, éxito o fallo.
 */
class ImportPostalCodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** El reintento automático no aplica: un archivo malo no mejora al reintentar. */
    public int $tries = 1;

    /** Amplio: parsear ~145 mil filas por streaming puede tardar. */
    public int $timeout = 600;

    /**
     * @param  string  $importId  UUID del registro PostalCodeImport (nombra el canal Reverb).
     * @param  string  $filePath  Ruta absoluta del archivo subido (TXT/CSV/ZIP).
     */
    public function __construct(
        public string $importId,
        public string $filePath,
    ) {}

    public function handle(
        PostalCodeImporter $importer,
        PostalCodeFileResolver $resolver,
        PostalCodeCatalogSwapper $swapper,
    ): void {
        $import = PostalCodeImport::find($this->importId);
        if (! $import) {
            return;
        }

        $resolvedPath = null;

        try {
            // Señal inmediata para la barra de progreso (antes del primer lote).
            event(new PostalCodeImportProgress(
                importId: $import->id,
                phase: 'parsing',
                processed: 0,
            ));

            // Resuelve el archivo: descomprime el ZIP oficial si aplica, o usa el TXT/CSV.
            $resolvedPath = $resolver->resolve($this->filePath);

            // Parseo a staging con progreso por lotes.
            $result = $importer->importToTable(
                $resolvedPath,
                (string) config('postal_codes.staging_table', 'postal_codes_staging'),
                function (int $rowsProcessed) use ($import): void {
                    event(new PostalCodeImportProgress(
                        importId: $import->id,
                        phase: 'parsing',
                        processed: $rowsProcessed,
                    ));
                },
            );

            // Validación de conteo mínimo antes de habilitar el swap.
            event(new PostalCodeImportProgress(
                importId: $import->id,
                phase: 'validating',
            ));
            $validation = $swapper->validateStaging();

            $baseData = [
                'rows_count' => $result->rowsCount,
                'states_count' => $result->statesCount,
                'municipalities_count' => $result->municipalitiesCount,
                'sample' => $result->sample,
            ];

            if (! $validation['ok']) {
                $marked = $this->markIfPending($import->id, array_merge($baseData, [
                    'status' => PostalCodeImportStatus::REJECTED,
                    'rejection_reason' => $validation['reason'],
                ]));

                if ($marked) {
                    event(new PostalCodeImportProgress(
                        importId: $import->id,
                        phase: 'rejected',
                        reason: $validation['reason'],
                    ));
                }

                return;
            }

            $marked = $this->markIfPending($import->id, array_merge($baseData, [
                'status' => PostalCodeImportStatus::PARSED,
                'rejection_reason' => null,
            ]));

            if ($marked) {
                event(new PostalCodeImportProgress(
                    importId: $import->id,
                    phase: 'ready',
                    rows: $result->rowsCount,
                    states: $result->statesCount,
                    municipalities: $result->municipalitiesCount,
                ));
            }
        } catch (\Throwable $e) {
            $reason = mb_substr($e->getMessage(), 0, 500);

            $marked = $this->markIfPending($import->id, [
                'status' => PostalCodeImportStatus::REJECTED,
                'rejection_reason' => $reason,
            ]);

            if ($marked) {
                event(new PostalCodeImportProgress(
                    importId: $import->id,
                    phase: 'rejected',
                    reason: $reason,
                ));
            }
        } finally {
            // Limpia el dir de extracción del ZIP (si aplica) y el del archivo subido.
            // FileResolver::cleanup es no-op fuera de la base temporal controlada.
            if ($resolvedPath !== null) {
                $resolver->cleanup(dirname($resolvedPath));
            }
            $resolver->cleanup($this->uploadDir());
        }
    }

    /**
     * Escribe el estado final del import SOLO si sigue en PENDING_PARSE. Si el
     * usuario lo descartó (DISCARDED) mientras se parseaba, no lo revive.
     *
     * @param  array<string, mixed>  $data
     */
    private function markIfPending(string $importId, array $data): int
    {
        return PostalCodeImport::whereKey($importId)
            ->where('status', PostalCodeImportStatus::PENDING_PARSE)
            ->update($data);
    }

    /** Directorio temporal donde el controller guardó el archivo subido. */
    private function uploadDir(): string
    {
        $tmpPath = trim((string) config('postal_codes.tmp_path', 'tmp/postal-imports'), '/');

        return storage_path('app/'.$tmpPath.'/'.$this->importId);
    }
}
