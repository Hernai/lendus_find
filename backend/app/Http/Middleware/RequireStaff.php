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
                'message' => 'Debes iniciar sesión para acceder a este recurso.',
            ], 401);
        }

        if (!$user->isStaff()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'No tienes permiso para acceder al panel de administración.',
            ], 403);
        }

        return $next($request);
    }
}
