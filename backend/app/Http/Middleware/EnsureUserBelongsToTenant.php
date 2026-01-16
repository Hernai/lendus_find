<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user belongs to the current tenant.
     * Uses fail-closed design: if tenant context is missing, denies access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        // If no authenticated user, let auth middleware handle it
        if ($user === null) {
            return $next($request);
        }

        // Tenant context must be set for multi-tenant routes
        if (!app()->bound('tenant.id')) {
            Log::warning('Tenant context not set for authenticated user', [
                'user_id' => $user->id,
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Configuration Error',
                'message' => 'Tenant context is required.',
            ], 400);
        }

        $tenantId = app('tenant.id');

        // Super admins can access any tenant
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Fail-closed: if tenant ID is null/empty, deny access
        if (empty($tenantId)) {
            return response()->json([
                'error' => 'Bad Request',
                'message' => 'Tenant identification required.',
            ], 400);
        }

        // Check if user belongs to the tenant
        if ($user->tenant_id !== $tenantId) {
            Log::warning('User attempted to access different tenant', [
                'user_id' => $user->id,
                'user_tenant' => $user->tenant_id,
                'requested_tenant' => $tenantId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have access to this tenant.',
            ], 403);
        }

        return $next($request);
    }
}
