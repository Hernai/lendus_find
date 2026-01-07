<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
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
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'You must be logged in to access this resource.',
            ], 401);
        }

        // Check if the permission method exists on User model
        if (!method_exists($user, $permission)) {
            return response()->json([
                'error' => 'Configuration Error',
                'message' => "Permission method '{$permission}' does not exist.",
            ], 500);
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
