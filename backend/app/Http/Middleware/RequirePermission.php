<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Allowed permission method names.
     *
     * This whitelist prevents arbitrary method invocation on the User model.
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
     * Checks if user has a specific permission.
     *
     * Usage in routes:
     *   ->middleware('permission:canApproveRejectApplications')
     *   ->middleware('permission:canManageProducts')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'You must be logged in to access this resource.',
            ], 401);
        }

        // Validate permission is in the allowed list (security: prevent arbitrary method calls)
        if (!in_array($permission, self::ALLOWED_PERMISSIONS, true)) {
            Log::warning('Invalid permission requested', [
                'permission' => $permission,
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Configuration Error',
                'message' => 'Invalid permission configuration.',
            ], 403);
        }

        // Check if the permission method exists on User model
        if (!method_exists($user, $permission)) {
            Log::error('Permission method not implemented', [
                'permission' => $permission,
            ]);

            return response()->json([
                'error' => 'Configuration Error',
                'message' => 'Permission not configured properly.',
            ], 403);
        }

        // Call the permission method
        if (!$user->$permission()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to perform this action.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
