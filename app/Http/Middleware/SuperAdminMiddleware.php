<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() instanceof \App\Models\User && $request->user()->estSuperAdmin()) {
            return $next($request);
        }

        abort(403, 'Réservé au super administrateur.');
    }
}
