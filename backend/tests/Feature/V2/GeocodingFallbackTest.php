<?php

namespace Tests\Feature\V2;

use App\Models\StaffAccount;
use App\Models\Tenant;
use App\Models\TenantApiConfig;
use App\Services\ExternalApi\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fallback de reverse-geocoding con OpenStreetMap/Nominatim
 * (cambio geocoding-fallback-osm): cadena Google → Nominatim → coords_only,
 * endpoint configurable por tenant, caché y gestión por el ADMIN.
 */
class GeocodingFallbackTest extends TestCase
{
    use RefreshDatabase;

    // $tenant lo declara Tests\TestCase (?Tenant).

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['slug' => 'test-geo', 'is_active' => true]);
    }

    /** Respuesta típica de Nominatim (reverse, addressdetails=1) en México. */
    private function nominatimBody(): array
    {
        return [
            'display_name' => 'Av. Reforma 123, Juárez, Cuauhtémoc, CDMX, 06600, México',
            'address' => [
                'road' => 'Avenida Reforma',
                'house_number' => '123',
                'neighbourhood' => 'Juárez',
                'city' => 'Ciudad de México',
                'municipality' => 'Cuauhtémoc',
                'state' => 'Ciudad de México',
                'postcode' => '06600',
            ],
        ];
    }

    private function googleBody(): array
    {
        return [
            'status' => 'OK',
            'results' => [[
                'formatted_address' => 'Av. Reforma 123, CDMX',
                'address_components' => [
                    ['long_name' => '06600', 'types' => ['postal_code']],
                    ['long_name' => 'Ciudad de México', 'types' => ['administrative_area_level_1']],
                    ['long_name' => 'Cuauhtémoc', 'types' => ['administrative_area_level_2']],
                    ['long_name' => 'Juárez', 'types' => ['sublocality_level_1', 'sublocality']],
                    ['long_name' => 'Avenida Reforma', 'types' => ['route']],
                    ['long_name' => '123', 'types' => ['street_number']],
                ],
            ]],
        ];
    }

    private function googleConfig(): void
    {
        TenantApiConfig::create([
            'tenant_id' => $this->tenant->id, 'provider' => 'google_maps',
            'service_type' => 'geocoding', 'api_key' => 'g-key', 'is_active' => true,
        ]);
    }

    // ===================== Cadena de resolución =====================

    public function test_sin_google_usa_nominatim(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response($this->nominatimBody(), 200)]);

        $res = (new GeocodingService($this->tenant))->reverseGeocode(19.4326, -99.1332);

        $this->assertSame('osm', $res['source']);
        $this->assertSame('Avenida Reforma', $res['street']);
        $this->assertSame('123', $res['ext_number']);
        $this->assertSame('Juárez', $res['neighborhood']);
        $this->assertSame('Cuauhtémoc', $res['municipality']);
        $this->assertSame('06600', $res['postal_code']);
    }

    public function test_nominatim_falla_cae_a_coords_only(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 500)]);

        $res = (new GeocodingService($this->tenant))->reverseGeocode(19.4, -99.1);

        $this->assertSame('coords_only', $res['source']);
        $this->assertArrayNotHasKey('street', $res);
    }

    public function test_google_configurado_responde(): void
    {
        $this->googleConfig();
        Http::fake(['maps.googleapis.com/*' => Http::response($this->googleBody(), 200)]);

        $res = (new GeocodingService($this->tenant))->reverseGeocode(19.43, -99.13);

        $this->assertSame('google', $res['source']);
        $this->assertSame('Avenida Reforma', $res['street']);
    }

    public function test_google_falla_cae_a_nominatim(): void
    {
        $this->googleConfig();
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []], 200),
            'nominatim.openstreetmap.org/*' => Http::response($this->nominatimBody(), 200),
        ]);

        $res = (new GeocodingService($this->tenant))->reverseGeocode(19.43, -99.13);

        $this->assertSame('osm', $res['source']);
    }

    // ===================== Caché, User-Agent, url_base =====================

    public function test_cachea_resultado_exitoso(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response($this->nominatimBody(), 200)]);
        $svc = new GeocodingService($this->tenant);

        $svc->reverseGeocode(19.4326, -99.1332);
        $svc->reverseGeocode(19.4326, -99.1332); // misma coord → caché

        Http::assertSentCount(1);
    }

    public function test_no_cachea_coords_only(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 500)]);
        $svc = new GeocodingService($this->tenant);

        $svc->reverseGeocode(19.4326, -99.1332);
        $svc->reverseGeocode(19.4326, -99.1332); // reintenta: coords_only no se cachea

        Http::assertSentCount(2);
    }

    public function test_incluye_user_agent_en_nominatim(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response($this->nominatimBody(), 200)]);

        (new GeocodingService($this->tenant))->reverseGeocode(19.4326, -99.1332);

        Http::assertSent(fn ($req) => $req->hasHeader('User-Agent')
            && str_contains($req->header('User-Agent')[0], 'LendusFind'));
    }

    public function test_usa_url_base_configurada(): void
    {
        TenantApiConfig::create([
            'tenant_id' => $this->tenant->id, 'provider' => 'nominatim',
            'service_type' => 'geocoding', 'is_active' => true,
            'extra_config' => ['base_url' => 'https://geo.mytenant.mx'],
        ]);
        Http::fake(['geo.mytenant.mx/*' => Http::response($this->nominatimBody(), 200)]);

        $res = (new GeocodingService($this->tenant))->reverseGeocode(19.4326, -99.1332);

        $this->assertSame('osm', $res['source']);
        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://geo.mytenant.mx/reverse'));
    }

    public function test_aislamiento_por_tenant(): void
    {
        // Tenant A con endpoint propio; el tenant actual (B) sin config usa el público.
        $tenantA = Tenant::factory()->create(['slug' => 'geo-a', 'is_active' => true]);
        TenantApiConfig::create([
            'tenant_id' => $tenantA->id, 'provider' => 'nominatim',
            'service_type' => 'geocoding', 'is_active' => true,
            'extra_config' => ['base_url' => 'https://geo.tenant-a.mx'],
        ]);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response($this->nominatimBody(), 200),
            'geo.tenant-a.mx/*' => Http::response($this->nominatimBody(), 200),
        ]);

        (new GeocodingService($this->tenant))->reverseGeocode(19.4326, -99.1332);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'nominatim.openstreetmap.org'));
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'geo.tenant-a.mx'));
    }

    // ===================== Gestión por el ADMIN =====================

    private function asAdmin(): static
    {
        $admin = StaffAccount::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $token = $admin->createToken('test', ['staff'])->plainTextToken;

        return $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_admin_guarda_config_nominatim(): void
    {
        $this->asAdmin()->postJson('/api/v2/staff/config/api-configs', [
            'provider' => 'nominatim', 'service_type' => 'geocoding',
            'extra_config' => ['base_url' => 'https://geo.mytenant.mx'], 'is_active' => true,
        ])->assertOk();

        $this->assertDatabaseHas('tenant_api_configs', [
            'tenant_id' => $this->tenant->id, 'provider' => 'nominatim', 'service_type' => 'geocoding',
        ]);
    }

    public function test_analyst_no_puede_configurar(): void
    {
        $analyst = StaffAccount::factory()->analyst()->create(['tenant_id' => $this->tenant->id]);
        $token = $analyst->createToken('test', ['staff'])->plainTextToken;

        $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v2/staff/config/api-configs', [
                'provider' => 'nominatim', 'service_type' => 'geocoding', 'is_active' => true,
            ])->assertForbidden();
    }

    public function test_url_base_invalida_es_422(): void
    {
        $this->asAdmin()->postJson('/api/v2/staff/config/api-configs', [
            'provider' => 'nominatim', 'service_type' => 'geocoding',
            'extra_config' => ['base_url' => 'no-es-una-url'], 'is_active' => true,
        ])->assertStatus(422);
    }

    public function test_provider_fuera_de_catalogo_es_422(): void
    {
        $this->asAdmin()->postJson('/api/v2/staff/config/api-configs', [
            'provider' => 'provider_inexistente', 'service_type' => 'geocoding', 'is_active' => true,
        ])->assertStatus(422);
    }
}
