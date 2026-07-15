<?php

namespace App\Services\ExternalApi;

use App\Models\Tenant;
use App\Models\TenantApiConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Geocodificación inversa (lat/lng → dirección) para el botón "Estoy en mi
 * domicilio" del onboarding.
 *
 * Cadena de proveedores: Google → Nominatim (OpenStreetMap) → coords_only.
 * Nominatim entra cuando el tenant no tiene Google configurado O Google falla,
 * y por defecto usa el endpoint público (sin API key); un tenant puede apuntar
 * a su propio Nominatim/LocationIQ vía TenantApiConfig (provider `nominatim`).
 * Si ningún proveedor resuelve, devuelve solo las coordenadas (source
 * coords_only) y el CP autollena el resto vía SEPOMEX en el frontend.
 */
class GeocodingService
{
    /** Endpoint público de Nominatim (default sin API key). */
    private const NOMINATIM_PUBLIC = 'https://nominatim.openstreetmap.org';

    /** User-Agent identificable exigido por la política de uso de Nominatim. */
    private const USER_AGENT = 'LendusFind/1.0 (+https://lendus.app; soporte@lendus.app)';

    /** TTL de caché: 30 días (la dirección de una coordenada no cambia). */
    private const CACHE_TTL = 60 * 60 * 24 * 30;

    public function __construct(private Tenant $tenant)
    {
    }

    /**
     * Geocodificación inversa con caché por coordenada redondeada (~100 m). Solo
     * se cachean resultados exitosos: un coords_only (proveedor caído o sin
     * resultado) no se cachea, para permitir reintento.
     *
     * @return array<string, mixed>
     */
    public function reverseGeocode(float $lat, float $lng): array
    {
        $key = sprintf('geocode:%s:%.3f:%.3f', $this->tenant->id, $lat, $lng);

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $result = $this->resolve($lat, $lng);

        if (($result['source'] ?? 'coords_only') !== 'coords_only') {
            Cache::put($key, $result, self::CACHE_TTL);
        }

        return $result;
    }

    /**
     * Ejecuta la cadena Google → Nominatim → coords_only.
     *
     * @return array<string, mixed>
     */
    private function resolve(float $lat, float $lng): array
    {
        if ($config = $this->googleConfig()) {
            if ($google = $this->tryGoogle($lat, $lng, $config)) {
                return $google;
            }
        }

        if ($osm = $this->tryNominatim($lat, $lng)) {
            return $osm;
        }

        return ['source' => 'coords_only', 'lat' => $lat, 'lng' => $lng];
    }

    private function googleConfig(): ?TenantApiConfig
    {
        $config = TenantApiConfig::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('provider', 'google_maps')
            ->where('service_type', 'geocoding')
            ->where('is_active', true)
            ->first();

        return ($config && ! empty($config->api_key)) ? $config : null;
    }

    private function nominatimConfig(): ?TenantApiConfig
    {
        return TenantApiConfig::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('provider', 'nominatim')
            ->where('service_type', 'geocoding')
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryGoogle(float $lat, float $lng, TenantApiConfig $config): ?array
    {
        try {
            $resp = Http::timeout(12)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$lat},{$lng}",
                'key' => $config->api_key,
                'language' => 'es',
                'region' => 'mx',
            ]);

            $data = $resp->successful() ? ($resp->json() ?? []) : [];
            $result = $data['results'][0] ?? null;

            if (($data['status'] ?? '') !== 'OK' || ! $result) {
                Log::info('Geocoding: sin resultado de Google', [
                    'tenant_id' => $this->tenant->id,
                    'status' => $data['status'] ?? 'no_status',
                ]);

                return null;
            }

            return array_merge(
                ['source' => 'google', 'lat' => $lat, 'lng' => $lng, 'formatted_address' => $result['formatted_address'] ?? null],
                $this->parseGoogleComponents($result['address_components'] ?? []),
            );
        } catch (\Throwable $e) {
            Log::warning('Geocoding: error con Google', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Nominatim/OSM (o LocationIQ si el tenant configura url_base + api_key).
     *
     * @return array<string, mixed>|null
     */
    private function tryNominatim(float $lat, float $lng): ?array
    {
        $config = $this->nominatimConfig();
        $baseUrl = is_array($config?->extra_config) ? ($config->extra_config['base_url'] ?? null) : null;
        $baseUrl = is_string($baseUrl) && $baseUrl !== '' ? rtrim($baseUrl, '/') : self::NOMINATIM_PUBLIC;

        $query = [
            'format' => 'jsonv2',
            'lat' => $lat,
            'lon' => $lng,
            'addressdetails' => 1,
            'accept-language' => 'es',
        ];
        // LocationIQ (misma API que Nominatim) autentica por parámetro `key`.
        if ($config && ! empty($config->api_key)) {
            $query['key'] = $config->api_key;
        }

        try {
            $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(12)
                ->get($baseUrl . '/reverse', $query);

            if (! $resp->successful()) {
                return null;
            }

            $data = $resp->json() ?? [];
            $address = $data['address'] ?? null;

            if (! is_array($address) || empty($address)) {
                Log::info('Geocoding: sin resultado de Nominatim', [
                    'tenant_id' => $this->tenant->id,
                ]);

                return null;
            }

            return array_merge(
                ['source' => 'osm', 'lat' => $lat, 'lng' => $lng, 'formatted_address' => $data['display_name'] ?? null],
                $this->parseNominatimAddress($address),
            );
        } catch (\Throwable $e) {
            Log::warning('Geocoding: error con Nominatim', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extrae los address_components de Google a nuestro esquema.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, ?string>
     */
    private function parseGoogleComponents(array $components): array
    {
        $by = function (string ...$types) use ($components): ?string {
            foreach ($types as $type) {
                foreach ($components as $c) {
                    if (in_array($type, $c['types'] ?? [], true)) {
                        return $c['long_name'] ?? null;
                    }
                }
            }
            return null;
        };

        return [
            'postal_code' => $by('postal_code'),
            'state' => $by('administrative_area_level_1'),
            // En México el municipio suele ser admin_area_level_2 o la localidad.
            'municipality' => $by('administrative_area_level_2', 'locality'),
            'city' => $by('locality', 'administrative_area_level_3'),
            'neighborhood' => $by('sublocality_level_1', 'sublocality', 'neighborhood'),
            'street' => $by('route'),
            'ext_number' => $by('street_number'),
        ];
    }

    /**
     * Mapea el objeto `address` de Nominatim (addressdetails=1) a nuestro
     * esquema, usando los alias comunes en México.
     *
     * @param  array<string, mixed>  $a
     * @return array<string, ?string>
     */
    private function parseNominatimAddress(array $a): array
    {
        $pick = function (string ...$keys) use ($a): ?string {
            foreach ($keys as $k) {
                if (! empty($a[$k]) && is_string($a[$k])) {
                    return $a[$k];
                }
            }
            return null;
        };

        return [
            'postal_code' => $pick('postcode'),
            'state' => $pick('state'),
            'municipality' => $pick('municipality', 'city_district', 'county'),
            'city' => $pick('city', 'town', 'village'),
            'neighborhood' => $pick('neighbourhood', 'suburb', 'quarter', 'residential'),
            'street' => $pick('road', 'pedestrian', 'footway'),
            'ext_number' => $pick('house_number'),
        ];
    }
}
