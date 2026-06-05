<?php

namespace App\Http\Controllers\Api\V2\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        // Cache server-side por tenant — invalidado por V2ConfigCacheObserver
        // al guardar/borrar TenantBranding. El Cache-Control de abajo cachea
        // tambien browser-side y en CDN intermedios.
        $manifest = Cache::remember(
            "v2:manifest:" . ($tenant?->id ?: 'default'),
            300,
            fn () => $this->build($tenant)
        );

        return response()
            ->json($manifest)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=300');
    }

    private function build(?Tenant $tenant): array
    {
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

        if (empty($icons) && $logo) {
            $icons[] = ['src' => $logo, 'sizes' => '512x512', 'type' => 'image/png'];
        }

        return [
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
    }
}
