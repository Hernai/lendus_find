<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaff
{
    /**
     * Handle an incoming request.
     *
     * Ensures the user is staff (agent, analyst, admin, or super_admin).
     * This is the minimum requirement to access the admin panel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'You must be logged in to access this resource.',
            ], 401);
        }

        if (!$user->isStaff()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to access the admin panel.',
            ], 403);
        }

        return $next($request);
    }
}
