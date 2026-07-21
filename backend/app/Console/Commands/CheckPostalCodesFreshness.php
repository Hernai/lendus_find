<?php

namespace App\Console\Commands;

use App\Models\PostalCode;
use App\Services\PostalCode\PostalCodeImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Verifica que el catálogo SEPOMEX (postal_codes) esté poblado y vigente.
 *
 * Corre programado (routes/console.php). Emite una alerta (log + salida) cuando:
 *  - la tabla está vacía, o
 *  - la última importación (marca que registra `postal-codes:import`) superó la
 *    ventana de vigencia configurable (services.postal_codes.max_age_days), o
 *  - está poblada pero sin marca de importación (vigencia indeterminada).
 *
 * La re-importación es MANUAL: descargar el archivo oficial de Correos de México
 * (requiere registro) y correr `postal-codes:import <archivo> --truncate`.
 */
class CheckPostalCodesFreshness extends Command
{
    protected $signature = 'postal-codes:check-freshness';

    protected $description = 'Alerta si el catálogo SEPOMEX (postal_codes) está vacío o la última importación venció la ventana de vigencia.';

    public function handle(): int
    {
        $count = PostalCode::count();

        // Catálogo vacío → alerta (requiere importación inicial).
        if ($count === 0) {
            return $this->emitAlert(
                'Catálogo de códigos postales (postal_codes) VACÍO: requiere importación con `postal-codes:import`.'
            );
        }

        $mark = Cache::get(PostalCodeImporter::LAST_IMPORT_CACHE_KEY);
        $rawAt = is_array($mark) ? ($mark['at'] ?? null) : null;

        // Poblado pero sin marca → vigencia indeterminada, alerta conservadora.
        if (! is_string($rawAt) || $rawAt === '') {
            return $this->emitAlert(
                "Catálogo de códigos postales poblado ({$count} filas) pero SIN marca de última importación: "
                . 're-importa con `postal-codes:import` para registrar su vigencia.'
            );
        }

        try {
            $lastImportAt = CarbonImmutable::parse($rawAt);
        } catch (\Throwable) {
            return $this->emitAlert(
                "Catálogo de códigos postales con marca de importación ilegible ('{$rawAt}'): re-importa con `postal-codes:import`."
            );
        }

        $maxAgeDays = (int) config('services.postal_codes.max_age_days', 180);
        // abs() para no depender del signo del diff entre versiones de Carbon.
        $ageDays = (int) abs($lastImportAt->diffInDays(CarbonImmutable::now()));

        // Importación vieja → alerta.
        if ($ageDays > $maxAgeDays) {
            return $this->emitAlert(
                "Catálogo de códigos postales DESACTUALIZADO: última importación hace {$ageDays} días "
                . "(máximo {$maxAgeDays}). Re-importa con `postal-codes:import`."
            );
        }

        // Poblado y vigente → sin alerta.
        $this->info("Catálogo de códigos postales OK: {$count} filas, última importación hace {$ageDays} días (máximo {$maxAgeDays}).");

        return self::SUCCESS;
    }

    /** Emite la alerta por log (warning) y salida de consola, devolviendo FAILURE. */
    private function emitAlert(string $message): int
    {
        $this->warn($message);
        Log::warning($message, ['command' => $this->signature]);

        return self::FAILURE;
    }
}
