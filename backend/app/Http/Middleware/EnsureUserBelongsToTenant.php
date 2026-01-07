<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user belongs to the current tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = app('tenant.id');

        if (!$user || !$tenantId) {
            return $next($request);
        }

        // Super admins can access any tenant
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user belongs to the tenant
        if ($user->tenant_id !== $tenantId) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have access to this tenant.',
            ], 403);
        }

        return $next($request);
    }
}
