<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * Identifies the tenant from:
     * 1. X-Tenant-ID header
     * 2. Subdomain
     * 3. Query parameter (for API testing)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'Unable to identify the tenant for this request.',
            ], 404);
        }

        if (!$tenant->is_active) {
            return response()->json([
                'error' => 'Tenant inactive',
                'message' => 'This tenant account is currently inactive.',
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
        if ($tenantId = $request->header('X-Tenant-ID')) {
            // Try by slug first (most common)
            $tenant = Tenant::where('slug', $tenantId)->first();
            if ($tenant) {
                return $tenant;
            }

            // Only try by UUID if it looks like a valid UUID format
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenantId)) {
                return Tenant::find($tenantId);
            }

            return null;
        }

        // 2. Check subdomain
        $host = $request->getHost();
        $subdomain = $this->extractSubdomain($host);

        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
            return Tenant::where('slug', $subdomain)->first();
        }

        // 3. Check query parameter (for development/testing)
        if (app()->environment('local', 'testing')) {
            if ($slug = $request->query('tenant')) {
                return Tenant::where('slug', $slug)->first();
            }
        }

        // 4. Default tenant for development
        if (app()->environment('local')) {
            return Tenant::first();
        }

        return null;
    }

    /**
     * Extract subdomain from host.
     */
    protected function extractSubdomain(string $host): ?string
    {
        // Remove port if present
        $host = explode(':', $host)[0];

        // Get parts
        $parts = explode('.', $host);

        // Handle localhost
        if ($host === 'localhost' || count($parts) < 3) {
            return null;
        }

        // Return first part (subdomain)
        return $parts[0];
    }
}
