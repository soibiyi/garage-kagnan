<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint une route à un ou plusieurs rôles : ->middleware('role:secretaire') ou 'role:admin,secretaire'.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Le super administrateur passe partout.
        if ($request->user() && (in_array($request->user()->role, $roles, true) || ($request->user() instanceof \App\Models\User && $request->user()->estSuperAdmin()))) {
            return $next($request);
        }

        abort(403, 'Accès non autorisé.');
    }
}
