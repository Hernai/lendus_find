<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Reserved subdomains that should not be treated as tenant slugs.
     *
     * `apifind` es el host del backend en producción (apifind.lendus.app); las
     * llamadas API identifican el tenant por el header `X-Tenant-ID`, no por
     * el subdominio del backend.
     */
    private const RESERVED_SUBDOMAINS = ['www', 'api', 'apifind', 'admin', 'app', 'mail', 'smtp'];

    /**
     * Handle an incoming request.
     *
     * Identifies the tenant from:
     * 1. X-Tenant-ID header (slug or UUID)
     * 2. Subdomain
     * 3. Query parameter (for development/testing only)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'No se pudo identificar el tenant para esta solicitud.',
            ], 404);
        }

        if (!$tenant->is_active) {
            return response()->json([
                'error' => 'Tenant inactive',
                'message' => 'Esta cuenta está actualmente inactiva.',
            ], 403);
        }

        // Store tenant in container for global access
        app()->instance('tenant', $tenant);
        app()->instance('tenant.id', $tenant->id);

        // Store tenant in request attributes for controller access
        $request->attributes->set('tenant', $tenant);

        // Share tenant with views
        view()->share('tenant', $tenant);

        return $next($request);
    }

    /**
     * Resolve the tenant from the request.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // 1. Check header (highest priority for API calls)
        $tenantId = $request->header('X-Tenant-ID');
        if ($tenantId !== null && $tenantId !== '') {
            return $this->findTenantByIdOrSlug($tenantId);
        }

        // 2. Check subdomain
        $subdomain = $this->extractSubdomain($request->getHost());
        if ($subdomain !== null) {
            return $this->findTenantByIdOrSlug($subdomain);
        }

        // 3. Check query parameter (for development/testing only)
        if (app()->environment('local', 'testing')) {
            $slug = $request->query('tenant');
            if ($slug !== null && $slug !== '') {
                return $this->findTenantByIdOrSlug($slug);
            }

            // 4. Default tenant for development (convenience for local dev)
            if (app()->environment('local')) {
                return Tenant::first();
            }
        }

        return null;
    }

    /**
     * Find a tenant by UUID or slug, con cache de 10 minutos.
     *
     * IdentifyTenant corre en TODAS las requests, así que un lookup sin
     * cache pega a la DB en cada request. La tabla `tenants` cambia muy
     * poco (cuando se crea o desactiva un SOFOM). El cache se invalida
     * en el modelo `Tenant` (eventos `saved`/`deleted`) para no servir
     * datos viejos.
     *
     * TTL de 600s = 10 min: balance entre frescura y carga de DB.
     */
    protected function findTenantByIdOrSlug(string $identifier): ?Tenant
    {
        return Cache::remember(
            "tenant:lookup:{$identifier}",
            600,
            function () use ($identifier): ?Tenant {
                // Try by slug first (most common and faster)
                $tenant = Tenant::where('slug', $identifier)->first();
                if ($tenant !== null) {
                    return $tenant;
                }

                // Try by UUID if it's a valid UUID format
                if (Str::isUuid($identifier)) {
                    return Tenant::find($identifier);
                }

                return null;
            }
        );
    }

    /**
     * Extract subdomain from host.
     *
     * @param string $host The request host (e.g., "tenant.example.com")
     * @return string|null The subdomain, or null if not applicable
     */
    protected function extractSubdomain(string $host): ?string
    {
        // Remove port if present
        $host = Str::before($host, ':');

        // Handle localhost (no subdomain)
        if ($host === 'localhost') {
            return null;
        }

        // Get parts
        $parts = explode('.', $host);

        // Need at least 3 parts for subdomain (e.g., tenant.example.com)
        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        // Skip reserved subdomains
        if (in_array(strtolower($subdomain), self::RESERVED_SUBDOMAINS, true)) {
            return null;
        }

        return $subdomain;
    }
}
