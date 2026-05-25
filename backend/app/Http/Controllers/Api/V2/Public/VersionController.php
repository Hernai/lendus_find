<?php

namespace App\Http\Controllers\Api\V2\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v2/public/version
 *
 * Permite a la app móvil consultar:
 * - `api_version`: versión actual del API.
 * - `min_app_version`: versión mínima soportada — debajo de eso debe
 *   forzar update.
 * - `latest_app_version`: última versión disponible en stores.
 * - `force_update`: indica si la versión cliente (header X-App-Version)
 *   está por debajo del mínimo.
 *
 * Los valores live-config residen en `config/app.php` bajo la clave
 * `mobile_versions`, con secciones opcionales por plataforma (`ios`/`android`).
 */
class VersionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $apiVersion = config('app.api_version', '2.0.0');
        $config = config('app.mobile_versions', []);
        $platform = strtolower((string) $request->header('X-Platform', 'web'));

        $platformConfig = $config[$platform] ?? $config['default'] ?? [
            'min' => '1.0.0',
            'latest' => '1.0.0',
        ];

        $clientVersion = (string) $request->header('X-App-Version', '');
        $minVersion = (string) ($platformConfig['min'] ?? '1.0.0');
        $latestVersion = (string) ($platformConfig['latest'] ?? '1.0.0');

        $forceUpdate = $clientVersion !== '' && version_compare($clientVersion, $minVersion, '<');

        return response()->json([
            'api_version' => $apiVersion,
            'min_app_version' => $minVersion,
            'latest_app_version' => $latestVersion,
            'force_update' => $forceUpdate,
            'platform' => $platform,
        ]);
    }
}
