<?php

namespace Tests\Feature\V2;

use App\Console\Commands\ImportPostalCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Catálogo de municipios por estado desde SEPOMEX (capability
 * catalogo-municipios-sepomex): endpoint público de municipios distintos y
 * ordenados, resolución de la clave del enum MexicanState → clave INEGI del
 * catálogo (fix CDMX≠CMX), y comando de vigencia del catálogo.
 */
class PostalCodeMunicipalitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El endpoint vive en el grupo público (requiere tenant + metadata).
        $this->setUpTenant();
    }

    /** Inserta una fila de catálogo (postal_codes es tabla de referencia global). */
    private function insertPostalCode(string $cp, string $estadoClave, string $estado, string $municipio): void
    {
        DB::table('postal_codes')->insert([
            'cp' => $cp,
            'asentamiento' => 'Colonia '.$municipio,
            'tipo_asentamiento' => 'Colonia',
            'municipio' => $municipio,
            'estado' => $estado,
            'ciudad' => $municipio,
            'estado_clave' => $estadoClave,
            'municipio_clave' => '001',
        ]);
    }

    public function test_estado_con_datos_devuelve_municipios_unicos_y_ordenados(): void
    {
        // Jalisco = clave INEGI '14'. Duplicamos Guadalajara (fila-por-colonia).
        $this->insertPostalCode('44100', '14', 'Jalisco', 'Guadalajara');
        $this->insertPostalCode('44110', '14', 'Jalisco', 'Guadalajara');
        $this->insertPostalCode('45000', '14', 'Jalisco', 'Zapopan');
        $this->insertPostalCode('45500', '14', 'Jalisco', 'Tlaquepaque');
        // Otro estado no debe filtrarse.
        $this->insertPostalCode('64000', '19', 'Nuevo León', 'Monterrey');

        $response = $this->withTenant()->getJson('/api/v2/public/postal-codes/municipios/JAL');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', 'JAL');

        $municipios = $response->json('data.municipios');
        $this->assertSame(['Guadalajara', 'Tlaquepaque', 'Zapopan'], $municipios);
    }

    public function test_estado_sin_datos_devuelve_lista_vacia_200(): void
    {
        // Sin filas para Yucatán ('31').
        $this->insertPostalCode('44100', '14', 'Jalisco', 'Guadalajara');

        $response = $this->withTenant()->getJson('/api/v2/public/postal-codes/municipios/YUC');

        $response->assertStatus(200)
            ->assertJsonPath('data.municipios', []);
    }

    public function test_estado_inexistente_no_da_500_sino_lista_vacia(): void
    {
        $response = $this->withTenant()->getJson('/api/v2/public/postal-codes/municipios/ZZZ');

        $response->assertStatus(200)
            ->assertJsonPath('data.municipios', []);
    }

    public function test_cdmx_devuelve_demarcaciones_no_vacio_por_clave_canonica(): void
    {
        // CDMX = clave INEGI '09'. El bug histórico usaba 'CMX' (nunca empataba).
        $this->insertPostalCode('06000', '09', 'Ciudad de México', 'Cuauhtémoc');
        $this->insertPostalCode('03100', '09', 'Ciudad de México', 'Benito Juárez');

        $response = $this->withTenant()->getJson('/api/v2/public/postal-codes/municipios/CDMX');

        $response->assertStatus(200)
            ->assertJsonPath('data.estado', 'CDMX');

        $municipios = $response->json('data.municipios');
        $this->assertNotEmpty($municipios);
        $this->assertContains('Cuauhtémoc', $municipios);
        $this->assertSame(['Benito Juárez', 'Cuauhtémoc'], $municipios);
    }

    public function test_freshness_alerta_cuando_catalogo_vacio(): void
    {
        Log::spy();

        $this->artisan('postal-codes:check-freshness')
            ->assertExitCode(1);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_freshness_alerta_cuando_import_es_viejo(): void
    {
        $this->insertPostalCode('44100', '14', 'Jalisco', 'Guadalajara');
        // Marca de importación vencida (más de 180 días por default).
        Cache::forever(ImportPostalCodes::LAST_IMPORT_CACHE_KEY, [
            'at' => now()->subDays(400)->toIso8601String(),
            'count' => 1,
        ]);

        Log::spy();

        $this->artisan('postal-codes:check-freshness')
            ->assertExitCode(1);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_freshness_no_alerta_cuando_poblado_y_vigente(): void
    {
        $this->insertPostalCode('44100', '14', 'Jalisco', 'Guadalajara');
        Cache::forever(ImportPostalCodes::LAST_IMPORT_CACHE_KEY, [
            'at' => now()->subDays(10)->toIso8601String(),
            'count' => 1,
        ]);

        Log::spy();

        $this->artisan('postal-codes:check-freshness')
            ->assertExitCode(0);

        Log::shouldNotHaveReceived('warning');
    }
}
