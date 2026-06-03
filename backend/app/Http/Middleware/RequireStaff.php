<?php

namespace App\Http\Middleware;

use App\Models\StaffAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaff
{
    /**
     * Handle an incoming request.
     *
     * Garantiza que el usuario es staff (analyst+) Y que tiene acceso al
     * tenant solicitado:
     *  - SUPER_ADMIN global (tenant_id NULL) → puede acceder a cualquier tenant
     *  - Staff per-tenant → solo a su tenant asignado
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Debes iniciar sesión para acceder a este recurso.',
            ], 401);
        }

        if (!$user->isStaff()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'No tienes permiso para acceder al panel de administración.',
            ], 403);
        }

        // Cross-tenant guard: si el staff no es super admin global, debe
        // coincidir su tenant_id con el tenant resuelto en la petición.
        $isSuperAdminGlobal = $user instanceof StaffAccount
            && $user->tenant_id === null
            && $user->isSuperAdmin();

        if (!$isSuperAdminGlobal && app()->bound('tenant.id')) {
            $requestedTenantId = app('tenant.id');
            if ($user->tenant_id !== $requestedTenantId) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'No tienes acceso a este tenant.',
                ], 403);
            }
        }

        return $next($request);
    }
}
