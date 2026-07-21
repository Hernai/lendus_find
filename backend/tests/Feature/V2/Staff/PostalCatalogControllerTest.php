<?php

namespace Tests\Feature\V2\Staff;

use App\Enums\PostalCodeImportStatus;
use App\Models\PostalCodeImport;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Endpoints staff del catálogo global de códigos postales
 * (tareas 5.4 y 5.5 del cambio catalogo-cp-autoservicio).
 *
 * Cubre el candado de permiso `canConfigureTenant` (SUPER_ADMIN sí, ANALYST no),
 * el swap síncrono vía `apply`, el historial (`index`) y el rechazo de `apply`
 * sobre un import que no está en PARSED. La carga (`upload`) end-to-end depende
 * del job en cola y de un archivo real, así que aquí se prueba `apply` sobre un
 * import ya PARSED (poblando el staging directamente).
 */
class PostalCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected StaffAccount $superAdmin;
    protected StaffAccount $analyst;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'test-postal', 'is_active' => true]);
        $this->superAdmin = StaffAccount::factory()->superAdmin()->create(['tenant_id' => $this->tenant->id]);
        $this->analyst = StaffAccount::factory()->analyst()->create(['tenant_id' => $this->tenant->id]);
    }

    /** Autentica como el staff dado con header de tenant + token Sanctum. */
    private function authAs(StaffAccount $staff): static
    {
        $this->app['auth']->forgetGuards();
        $token = $staff->createToken('test', ['staff'])->plainTextToken;

        return $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * Crea un registro de importación en el estado dado.
     */
    private function makeImport(PostalCodeImportStatus $status): PostalCodeImport
    {
        $import = new PostalCodeImport([
            'staff_account_id' => $this->superAdmin->id,
            'origin_tenant_id' => $this->tenant->id,
            'origin_tenant_slug' => $this->tenant->slug,
            'original_filename' => 'CPdescarga.txt',
            'status' => $status,
            'rows_count' => 4,
            'states_count' => 3,
            'municipalities_count' => 3,
        ]);
        $import->id = (string) Str::uuid();
        $import->save();

        return $import;
    }

    /**
     * Puebla el staging con filas distinguibles por su cp (nuevo catálogo).
     */
    private function seedStaging(): void
    {
        DB::table('postal_codes_staging')->insert([
            ['cp' => '01000', 'asentamiento' => 'Centro', 'municipio' => 'Cuauhtémoc', 'estado' => 'Ciudad de México'],
            ['cp' => '44100', 'asentamiento' => 'Centro', 'municipio' => 'Guadalajara', 'estado' => 'Jalisco'],
            ['cp' => '64000', 'asentamiento' => 'Centro', 'municipio' => 'Monterrey', 'estado' => 'Nuevo León'],
            ['cp' => '97000', 'asentamiento' => 'Centro', 'municipio' => 'Mérida', 'estado' => 'Yucatán'],
        ]);
    }

    // =====================================================
    // 5.4 — Candado de permiso canConfigureTenant
    // =====================================================

    public function test_analista_sin_permiso_recibe_403_en_status_e_imports(): void
    {
        $this->authAs($this->analyst)
            ->getJson('/api/v2/staff/catalogs/postal-codes/status')
            ->assertForbidden();

        $this->authAs($this->analyst)
            ->getJson('/api/v2/staff/catalogs/postal-codes/imports')
            ->assertForbidden();
    }

    public function test_super_admin_puede_ver_status_e_imports(): void
    {
        $this->authAs($this->superAdmin)
            ->getJson('/api/v2/staff/catalogs/postal-codes/status')
            ->assertOk()
            ->assertJsonStructure(['data' => ['last_import', 'current_count']]);

        $this->authAs($this->superAdmin)
            ->getJson('/api/v2/staff/catalogs/postal-codes/imports')
            ->assertOk()
            ->assertJsonStructure(['data' => ['imports', 'meta']]);
    }

    // =====================================================
    // 5.5 — apply hace el swap; index lista; apply no-PARSED es 422
    // =====================================================

    public function test_apply_sobre_import_parsed_aplica_el_swap_y_lo_marca_aplicado(): void
    {
        // Umbrales bajos (por realismo; apply hace el swap sin revalidar el conteo).
        config(['postal_codes.min_rows' => 3, 'postal_codes.min_states' => 2]);

        // Catálogo vigente viejo, distinguible por su cp.
        DB::table('postal_codes')->insert([
            ['cp' => '90001', 'asentamiento' => 'Viejo', 'municipio' => 'Muni Vieja', 'estado' => 'Estado Viejo'],
        ]);
        $this->seedStaging();

        $import = $this->makeImport(PostalCodeImportStatus::PARSED);

        $response = $this->authAs($this->superAdmin)
            ->postJson("/api/v2/staff/catalogs/postal-codes/imports/{$import->id}/apply");

        $response->assertOk()
            ->assertJson(['data' => ['status' => 'APPLIED']]);

        // El import quedó APPLIED en base de datos.
        $this->assertSame(
            PostalCodeImportStatus::APPLIED,
            $import->refresh()->status
        );

        // postal_codes refleja el swap: ahora contiene las filas del staging.
        $this->assertSame(4, DB::table('postal_codes')->count());
        $this->assertSame(0, DB::table('postal_codes')->where('cp', '90001')->count());
        $this->assertSame(1, DB::table('postal_codes')->where('cp', '01000')->count());
        // El catálogo viejo quedó en staging tras el swap.
        $this->assertSame(1, DB::table('postal_codes_staging')->where('cp', '90001')->count());
    }

    public function test_index_lista_el_import_en_el_historial(): void
    {
        $import = $this->makeImport(PostalCodeImportStatus::APPLIED);

        $response = $this->authAs($this->superAdmin)
            ->getJson('/api/v2/staff/catalogs/postal-codes/imports');

        $response->assertOk();

        $ids = collect($response->json('data.imports'))->pluck('id')->all();
        $this->assertContains($import->id, $ids);
        $this->assertSame(1, $response->json('data.meta.total'));
    }

    public function test_apply_sobre_import_no_parsed_devuelve_422(): void
    {
        $import = $this->makeImport(PostalCodeImportStatus::PENDING_PARSE);

        $this->authAs($this->superAdmin)
            ->postJson("/api/v2/staff/catalogs/postal-codes/imports/{$import->id}/apply")
            ->assertStatus(422)
            ->assertJson(['error' => 'INVALID_STATE']);

        // El estado no cambió.
        $this->assertSame(
            PostalCodeImportStatus::PENDING_PARSE,
            $import->refresh()->status
        );
    }

    // =====================================================
    // Concurrencia / serialización (fixes de la revisión adversarial)
    // =====================================================

    public function test_upload_con_un_import_en_vuelo_devuelve_409(): void
    {
        Queue::fake(); // el job no debe correr; el rechazo ocurre antes de crearlo.
        $this->makeImport(PostalCodeImportStatus::PARSED); // import en vuelo ocupando la staging

        $file = UploadedFile::fake()->createWithContent('CPdescarga.txt', "d_codigo|d_asenta\n01000|Centro\n");

        $this->authAs($this->superAdmin)
            ->postJson('/api/v2/staff/catalogs/postal-codes/upload', ['file' => $file])
            ->assertStatus(409)
            ->assertJson(['error' => 'IMPORT_IN_PROGRESS']);

        // No se creó un segundo import ni se despachó el job de importación.
        $this->assertSame(1, PostalCodeImport::count());
        Queue::assertNotPushed(\App\Jobs\ImportPostalCodesJob::class);
    }

    public function test_doble_apply_no_deshace_el_swap(): void
    {
        config(['postal_codes.min_rows' => 3, 'postal_codes.min_states' => 2]);
        DB::table('postal_codes')->insert([
            ['cp' => '90001', 'asentamiento' => 'Viejo', 'municipio' => 'Muni Vieja', 'estado' => 'Estado Viejo'],
        ]);
        $this->seedStaging(); // 4 filas, coincide con rows_count=4 del import
        $import = $this->makeImport(PostalCodeImportStatus::PARSED);

        // Primer apply: aplica el swap.
        $this->authAs($this->superAdmin)
            ->postJson("/api/v2/staff/catalogs/postal-codes/imports/{$import->id}/apply")
            ->assertOk()
            ->assertJson(['data' => ['status' => 'APPLIED']]);

        // Segundo apply sobre el mismo import (ya APPLIED): rechazado, sin re-swap.
        $this->authAs($this->superAdmin)
            ->postJson("/api/v2/staff/catalogs/postal-codes/imports/{$import->id}/apply")
            ->assertStatus(422)
            ->assertJson(['error' => 'INVALID_STATE']);

        // El catálogo nuevo sigue vigente (el segundo apply NO revirtió el swap).
        $this->assertSame(4, DB::table('postal_codes')->count());
        $this->assertSame(0, DB::table('postal_codes')->where('cp', '90001')->count());
        $this->assertSame(PostalCodeImportStatus::APPLIED, $import->refresh()->status);
    }

    public function test_apply_con_staging_desincronizada_rechaza_sin_swap(): void
    {
        // La staging tiene MENOS filas de las que este import parseó (rows_count=4):
        // fue sobrescrita por otra carga. Con umbral mínimo permisivo, el rechazo
        // proviene del mismatch de conteo, no del umbral.
        config(['postal_codes.min_rows' => 1, 'postal_codes.min_states' => 1]);
        DB::table('postal_codes')->insert([
            ['cp' => '90001', 'asentamiento' => 'Vigente', 'municipio' => 'Muni', 'estado' => 'Estado'],
        ]);
        DB::table('postal_codes_staging')->insert([
            ['cp' => '01000', 'asentamiento' => 'A', 'municipio' => 'M1', 'estado' => 'E1'],
            ['cp' => '44100', 'asentamiento' => 'B', 'municipio' => 'M2', 'estado' => 'E2'],
        ]); // 2 filas != rows_count 4
        $import = $this->makeImport(PostalCodeImportStatus::PARSED);

        $this->authAs($this->superAdmin)
            ->postJson("/api/v2/staff/catalogs/postal-codes/imports/{$import->id}/apply")
            ->assertStatus(409)
            ->assertJson(['error' => 'STAGING_MISMATCH']);

        // El import quedó rechazado y el catálogo vigente NO se tocó.
        $this->assertSame(PostalCodeImportStatus::REJECTED, $import->refresh()->status);
        $this->assertSame(1, DB::table('postal_codes')->count());
        $this->assertSame(1, DB::table('postal_codes')->where('cp', '90001')->count());
    }

    public function test_discard_libera_un_import_parsed(): void
    {
        $import = $this->makeImport(PostalCodeImportStatus::PARSED);

        $this->authAs($this->superAdmin)
            ->postJson("/api/v2/staff/catalogs/postal-codes/imports/{$import->id}/discard")
            ->assertOk()
            ->assertJson(['data' => ['id' => $import->id]]);

        $this->assertSame(PostalCodeImportStatus::DISCARDED, $import->refresh()->status);

        // Ya no se puede aplicar un import descartado.
        $this->authAs($this->superAdmin)
            ->postJson("/api/v2/staff/catalogs/postal-codes/imports/{$import->id}/apply")
            ->assertStatus(422)
            ->assertJson(['error' => 'INVALID_STATE']);
    }

    public function test_discard_sobre_estado_no_descartable_devuelve_422(): void
    {
        $import = $this->makeImport(PostalCodeImportStatus::APPLIED);

        $this->authAs($this->superAdmin)
            ->postJson("/api/v2/staff/catalogs/postal-codes/imports/{$import->id}/discard")
            ->assertStatus(422)
            ->assertJson(['error' => 'INVALID_STATE']);

        $this->assertSame(PostalCodeImportStatus::APPLIED, $import->refresh()->status);
    }

    public function test_analista_sin_permiso_no_puede_descartar(): void
    {
        $import = $this->makeImport(PostalCodeImportStatus::PARSED);

        $this->authAs($this->analyst)
            ->postJson("/api/v2/staff/catalogs/postal-codes/imports/{$import->id}/discard")
            ->assertForbidden();
    }
}
