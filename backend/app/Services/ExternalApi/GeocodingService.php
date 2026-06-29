<?php

namespace App\Services\ExternalApi;

use App\Models\Tenant;
use App\Models\TenantApiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Geocodificación inversa (lat/lng → dirección) para el botón "Estoy en mi
 * domicilio" del onboarding.
 *
 * Si el tenant tiene Google Maps configurado en integraciones, usa la
 * Geocoding API de Google (calidad alta en México). Si no, devuelve solo las
 * coordenadas (source = coords_only): el frontend captura la ubicación y el CP
 * autollena el resto vía SEPOMEX.
 */
class GeocodingService
{
    public function __construct(private Tenant $tenant)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function reverseGeocode(float $lat, float $lng): array
    {
        $base = ['source' => 'coords_only', 'lat' => $lat, 'lng' => $lng];

        $config = TenantApiConfig::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('provider', 'google_maps')
            ->where('service_type', 'geocoding')
            ->where('is_active', true)
            ->first();

        if (!$config || empty($config->api_key)) {
            return $base;
        }

        try {
            $resp = Http::timeout(12)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$lat},{$lng}",
                'key' => $config->api_key,
                'language' => 'es',
                'region' => 'mx',
            ]);

            $data = $resp->successful() ? ($resp->json() ?? []) : [];
            $result = $data['results'][0] ?? null;

            if (($data['status'] ?? '') !== 'OK' || !$result) {
                Log::info('Geocoding: sin resultado de Google', [
                    'tenant_id' => $this->tenant->id,
                    'status' => $data['status'] ?? 'no_status',
                ]);

                return $base;
            }

            return array_merge($base, [
                'source' => 'google',
                'formatted_address' => $result['formatted_address'] ?? null,
            ], $this->parseComponents($result['address_components'] ?? []));
        } catch (\Throwable $e) {
            Log::warning('Geocoding: error con Google', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);

            return $base;
        }
    }

    /**
     * Extrae los campos de address_components de Google a nuestro esquema.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, ?string>
     */
    private function parseComponents(array $components): array
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
}
