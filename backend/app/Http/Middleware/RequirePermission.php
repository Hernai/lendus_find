<?php

namespace App\Http\Middleware;

use App\Models\StaffAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Allowed permission method names.
     *
     * This whitelist prevents arbitrary method invocation on StaffAccount models.
     */
    private const ALLOWED_PERMISSIONS = [
        'canReviewDocuments',
        'canVerifyReferences',
        'canChangeApplicationStatus',
        'canApproveRejectApplications',
        'canAssignApplications',
        'canManageProducts',
        'canManageUsers',
        'canViewReports',
        'canConfigureTenant',
        'canManageIntegrations',
    ];

    /**
     * Handle an incoming request.
     *
     * Checks if staff user has a specific permission.
     *
     * Usage in routes:
     *   ->middleware('permission:canApproveRejectApplications')
     *   ->middleware('permission:canManageProducts')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var StaffAccount|null $user */
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Debes iniciar sesión para acceder a este recurso.',
            ], 401);
        }

        // Validate permission is in the allowed list (security: prevent arbitrary method calls)
        if (!in_array($permission, self::ALLOWED_PERMISSIONS, true)) {
            Log::warning('Invalid permission requested', [
                'permission' => $permission,
                'user_id' => $user->id,
                'user_type' => get_class($user),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Configuration Error',
                'message' => 'Configuración de permiso inválida.',
            ], 403);
        }

        // Check if the permission method exists on the authenticated model (User or StaffAccount)
        if (!method_exists($user, $permission)) {
            Log::error('Permission method not implemented', [
                'permission' => $permission,
                'user_type' => get_class($user),
            ]);

            return response()->json([
                'error' => 'Configuration Error',
                'message' => 'Permiso no configurado correctamente.',
            ], 403);
        }

        // Call the permission method
        if (!$user->$permission()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'No tienes permiso para realizar esta acción.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
