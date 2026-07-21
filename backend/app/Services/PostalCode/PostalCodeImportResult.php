<?php

namespace App\Services\PostalCode;

/**
 * Resultado inmutable de un parseo de catálogo SEPOMEX a una tabla.
 *
 * Alimenta tanto el resumen del comando CLI como el preview y la validación de
 * conteo mínimo del flujo de panel (staging + swap). Los conteos se acumulan en
 * streaming durante la inserción, sin releer la tabla destino.
 */
final readonly class PostalCodeImportResult
{
    /**
     * @param  int  $rowsCount  Total de filas válidas insertadas.
     * @param  int  $statesCount  Número de valores distintos de `estado`.
     * @param  int  $municipalitiesCount  Número de valores distintos de `municipio`.
     * @param  array<int, array<string, string|null>>  $sample  Muestra (primeras ~10 filas mapeadas).
     * @param  int  $skipped  Filas omitidas por CP inválido.
     */
    public function __construct(
        public int $rowsCount,
        public int $statesCount,
        public int $municipalitiesCount,
        public array $sample,
        public int $skipped,
    ) {
    }
}
