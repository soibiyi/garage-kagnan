<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Parametres;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle d'accès global piloté par le super administrateur :
 *  - compte suspendu → déconnexion immédiate ;
 *  - mode maintenance → seuls les super administrateurs peuvent utiliser l'application
 *    (la page de connexion reste ouverte pour qu'ils puissent se connecter).
 */
class VerifierAccesApplication
{
    /** Chemins toujours accessibles (connexion / déconnexion, retour au compte du superadmin). */
    private const CHEMINS_LIBRES = ['login', 'logout', 'superadmin/retour', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->suspendu_at && ! $request->session()->has('impersonator_id')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Votre compte est suspendu' . ($user->suspendu_motif ? " : {$user->suspendu_motif}" : '.') . " Contactez l'administration.",
            ]);
        }

        if (Parametres::get('maintenance_active') && ! $request->is(...self::CHEMINS_LIBRES)) {
            $superAdmin = $user instanceof User && $user->estSuperAdmin();
            $impersonation = $request->hasSession() && $request->session()->has('impersonator_id');

            if (! $superAdmin && ! $impersonation) {
                // Navigation Inertia : rechargement complet pour afficher la page de maintenance
                if ($request->header('X-Inertia')) {
                    return \Inertia\Inertia::location($request->fullUrl());
                }

                return response()->view('maintenance', [
                    'message' => Parametres::get('maintenance_message'),
                    'connecte' => (bool) $user,
                ], 503);
            }
        }

        return $next($request);
    }
}
