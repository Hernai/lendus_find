<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\ExternalApi\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Geolocalización del aplicante (onboarding "Estoy en mi domicilio").
 */
class GeoController extends Controller
{
    use ApiResponses;

    /**
     * Geocodificación inversa: lat/lng → dirección.
     *
     * POST /v2/applicant/geo/reverse
     */
    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $account = $request->user();
        $tenant = Tenant::withoutGlobalScopes()->find($account->tenant_id);

        if (!$tenant) {
            return $this->notFound('Tenant no encontrado.');
        }

        $result = (new GeocodingService($tenant))->reverseGeocode(
            (float) $validated['lat'],
            (float) $validated['lng'],
        );

        return $this->success($result);
    }
}
