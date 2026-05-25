<?php

namespace App\Http\Controllers\Api\V2\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sirve el manifest PWA dinámico por tenant.
 *
 * Se invoca desde el `<link rel="manifest">` del frontend. El tenant se
 * resuelve por el middleware `tenant` (subdominio o `X-Tenant-ID`).
 *
 * El contenido se construye a partir de `TenantBranding`; si faltan
 * iconos o nombres específicos para PWA, cae al branding general (logo,
 * primary_color) o a valores neutros de LendusFind.
 */
class ManifestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        // Relación con tenant_branding (no la columna JSON legacy `branding`).
        $branding = $tenant?->brandingConfig;

        $name = $branding?->pwa_name ?: $tenant?->name ?: 'LendusFind';
        $shortName = mb_substr($branding?->pwa_short_name ?: $name, 0, 12);
        $themeColor = $branding?->pwa_theme_color ?: ($branding?->primary_color ?: '#1E40AF');
        $backgroundColor = $branding?->pwa_background_color
            ?: ($branding?->background_color ?: '#FFFFFF');

        $icon192 = $branding?->icon_192_url;
        $icon512 = $branding?->icon_512_url;
        $maskable = $branding?->maskable_icon_url;
        $logo = $branding?->logo_url;

        $icons = [];

        if ($icon192) {
            $icons[] = ['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png'];
        }
        if ($icon512) {
            $icons[] = ['src' => $icon512, 'sizes' => '512x512', 'type' => 'image/png'];
        }
        if ($maskable) {
            $icons[] = [
                'src' => $maskable,
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ];
        }

        // Fallback razonable si el tenant aún no subió iconos PWA dedicados.
        if (empty($icons) && $logo) {
            $icons[] = ['src' => $logo, 'sizes' => '512x512', 'type' => 'image/png'];
        }

        $manifest = [
            'name' => $name,
            'short_name' => $shortName,
            'description' => 'Solicita tu crédito en línea con '.$name,
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => $themeColor,
            'background_color' => $backgroundColor,
            'lang' => 'es-MX',
            'icons' => $icons,
        ];

        return response()
            ->json($manifest)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
