<?php

namespace Tests\Feature\V2;

use App\Services\PostalCode\PostalCodeCatalogSwapper;
use App\Services\PostalCode\PostalCodeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Validación de staging y swap atómico del catálogo de códigos postales
 * (tareas 3.4 y 3.5 del cambio catalogo-cp-autoservicio).
 *
 * Para no insertar las ~145 mil filas del catálogo nacional, los umbrales
 * mínimos (`postal_codes.min_rows` / `min_states`) se sobreescriben con
 * `config([...])` en cada prueba: alto para forzar el rechazo, bajo para
 * habilitar el swap con un puñado de filas.
 */
class PostalCodeCatalogSwapperTest extends TestCase
{
    use RefreshDatabase;

    private function swapper(): PostalCodeCatalogSwapper
    {
        return app(PostalCodeCatalogSwapper::class);
    }

    /**
     * Inserta filas directamente en la tabla destino (postal_codes o su staging).
     *
     * @param  array<int, array<string, string>>  $rows
     */
    private function insertRows(string $table, array $rows): void
    {
        DB::table($table)->insert($rows);
    }

    /**
     * Genera filas de staging con estados distintos para cubrir el conteo.
     *
     * @return array<int, array<string, string>>
     */
    private function stagingRows(): array
    {
        return [
            ['cp' => '01000', 'asentamiento' => 'Centro', 'municipio' => 'Cuauhtémoc', 'estado' => 'Ciudad de México'],
            ['cp' => '01010', 'asentamiento' => 'Guadalupe Inn', 'municipio' => 'Álvaro Obregón', 'estado' => 'Ciudad de México'],
            ['cp' => '44100', 'asentamiento' => 'Centro', 'municipio' => 'Guadalajara', 'estado' => 'Jalisco'],
            ['cp' => '64000', 'asentamiento' => 'Centro', 'municipio' => 'Monterrey', 'estado' => 'Nuevo León'],
        ];
    }

    // =====================================================
    // 3.4 — Staging por debajo del umbral: se rechaza y no se toca el catálogo
    // =====================================================

    public function test_validate_staging_rechaza_catalogo_truncado_sin_tocar_el_vigente(): void
    {
        // Umbral inalcanzable con pocas filas: el staging queda "truncado".
        config(['postal_codes.min_rows' => 1_000_000, 'postal_codes.min_states' => 32]);

        // Catálogo vigente con una fila distinguible para verificar que no cambia.
        $this->insertRows('postal_codes', [
            ['cp' => '99999', 'asentamiento' => 'Vigente', 'municipio' => 'Vigente', 'estado' => 'Estado Vigente'],
        ]);

        // Staging poblado por debajo del umbral.
        $this->insertRows('postal_codes_staging', $this->stagingRows());

        $result = $this->swapper()->validateStaging();

        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['reason']);
        $this->assertSame(4, $result['rows']);
        $this->assertSame(3, $result['states']);

        // El catálogo vigente permanece intacto (ni contenido ni conteo cambiaron).
        $this->assertSame(1, DB::table('postal_codes')->count());
        $this->assertSame('Vigente', DB::table('postal_codes')->value('asentamiento'));
    }

    public function test_validate_staging_rechaza_por_estados_insuficientes(): void
    {
        // Suficientes filas, pero muy pocos estados distintos.
        config(['postal_codes.min_rows' => 2, 'postal_codes.min_states' => 32]);

        $this->insertRows('postal_codes_staging', $this->stagingRows());

        $result = $this->swapper()->validateStaging();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('estados', $result['reason']);
        $this->assertSame(3, $result['states']);
    }

    // =====================================================
    // 3.5 — Staging sobre el umbral: swap intercambia catálogos y marca vigencia
    // =====================================================

    public function test_swap_intercambia_catalogos_y_actualiza_la_marca_de_vigencia(): void
    {
        // Umbrales bajos para habilitar el swap con pocas filas.
        config(['postal_codes.min_rows' => 3, 'postal_codes.min_states' => 2]);

        // Catálogo vigente (viejo): filas distinguibles por su cp.
        $oldRows = [
            ['cp' => '90001', 'asentamiento' => 'Viejo A', 'municipio' => 'Muni Vieja', 'estado' => 'Estado Viejo'],
            ['cp' => '90002', 'asentamiento' => 'Viejo B', 'municipio' => 'Muni Vieja', 'estado' => 'Estado Viejo'],
        ];
        $this->insertRows('postal_codes', $oldRows);

        // Staging (nuevo catálogo) sobre el umbral y con estados distintos.
        $this->insertRows('postal_codes_staging', $this->stagingRows());

        // Precondición: la validación aprueba el staging.
        $validation = $this->swapper()->validateStaging();
        $this->assertTrue($validation['ok']);
        $this->assertNull($validation['reason']);

        $this->swapper()->swap();

        // Tras el swap, `postal_codes` contiene lo que estaba en staging (4 filas).
        $this->assertSame(4, DB::table('postal_codes')->count());
        $newCps = DB::table('postal_codes')->pluck('cp')->sort()->values()->all();
        $this->assertSame(['01000', '01010', '44100', '64000'], $newCps);
        // Los cp viejos ya no están vigentes.
        $this->assertSame(0, DB::table('postal_codes')->whereIn('cp', ['90001', '90002'])->count());

        // Y `postal_codes_staging` conserva el catálogo viejo (2 filas).
        $this->assertSame(2, DB::table('postal_codes_staging')->count());
        $stagedCps = DB::table('postal_codes_staging')->pluck('cp')->sort()->values()->all();
        $this->assertSame(['90001', '90002'], $stagedCps);

        // La marca de vigencia se actualizó con el conteo del nuevo catálogo.
        $mark = Cache::get(PostalCodeImporter::LAST_IMPORT_CACHE_KEY);
        $this->assertIsArray($mark);
        $this->assertSame(4, $mark['count']);
        $this->assertArrayHasKey('at', $mark);
    }
}
