<?php

namespace App\Services\PostalCode;

use Illuminate\Support\Facades\DB;

/**
 * Valida el staging del catálogo de códigos postales y aplica el swap atómico
 * con la tabla vigente.
 *
 * El swap intercambia por RENAME (solo metadata) `postal_codes` ↔
 * `postal_codes_staging` dentro de una transacción: el lock ACCESS EXCLUSIVE
 * dura milisegundos y las consultas concurrentes nunca ven la tabla vacía o a
 * medias. Tras el swap, `postal_codes_staging` contiene el catálogo viejo.
 */
class PostalCodeCatalogSwapper
{
    /** Nombre temporal para el intercambio; debe estar libre durante el swap. */
    private const SWAP_TMP_TABLE = '_cp_swap_tmp';

    public function __construct(
        private readonly PostalCodeImporter $importer,
    ) {
    }

    /**
     * Valida que el staging alcance los umbrales mínimos de integridad
     * (config `postal_codes.min_rows` / `min_states`) antes de permitir el swap.
     *
     * @return array{ok: bool, reason: ?string, rows: int, states: int}
     */
    public function validateStaging(): array
    {
        $table = $this->stagingTable();

        $rows = (int) DB::table($table)->count();
        $states = (int) DB::table($table)->distinct()->count('estado');

        $minRows = (int) config('postal_codes.min_rows', 100000);
        $minStates = (int) config('postal_codes.min_states', 32);

        $reason = null;
        if ($rows < $minRows) {
            $reason = "El catálogo tiene {$rows} filas (mínimo {$minRows}): parece truncado.";
        } elseif ($states < $minStates) {
            $reason = "El catálogo tiene {$states} estados distintos (mínimo {$minStates}): parece incompleto.";
        }

        return [
            'ok' => $reason === null,
            'reason' => $reason,
            'rows' => $rows,
            'states' => $states,
        ];
    }

    /**
     * Aplica el swap atómico entre `postal_codes` y `postal_codes_staging` por
     * RENAME dentro de una transacción, y actualiza la marca de vigencia con el
     * conteo del nuevo catálogo.
     *
     * Si el swap falla, la transacción hace rollback y el catálogo vigente queda
     * intacto.
     */
    public function swap(): void
    {
        // El staging pasa a ser `postal_codes` tras el swap; su conteo es el del
        // nuevo catálogo vigente. Se lee antes del RENAME.
        $count = (int) DB::table($this->stagingTable())->count();

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE postal_codes RENAME TO ' . self::SWAP_TMP_TABLE);
            DB::statement('ALTER TABLE postal_codes_staging RENAME TO postal_codes');
            DB::statement('ALTER TABLE ' . self::SWAP_TMP_TABLE . ' RENAME TO postal_codes_staging');
        });

        // Marca de vigencia del catálogo recién aplicado (misma llave que el CLI).
        $this->importer->touchFreshness($count);
    }

    private function stagingTable(): string
    {
        return (string) config('postal_codes.staging_table', 'postal_codes_staging');
    }
}
