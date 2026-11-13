<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Journal d'activité des utilisateurs connectés, consulté en direct par le super administrateur.
 *  - présence : last_seen_at mis à jour (au plus une fois toutes les 30 s) ;
 *  - journal : pages ouvertes (visites Inertia) et toutes les actions (POST/PUT/PATCH/DELETE).
 *    Les appels de rafraîchissement automatique (polling JSON en GET) ne sont pas journalisés.
 */
class TrackUserActivity
{
    /** Routes jamais journalisées (supervision elle-même, rafraîchissements, positions GPS). */
    private const ROUTES_IGNOREES = [
        'superadmin.supervision.*',
        'superadmin.erreurs.flux',
        'superadmin.alertes',
        'erreurs.client',
        '*.notifications*',
        '*.position',
        'secretariat.chat.index',
    ];

    private const CHAMPS_SENSIBLES = '/pass|token|secret|code_?verif|cvv|carte/i';

    public function handle(Request $request, Closure $next): Response
    {
        $debut = microtime(true);
        $response = $next($request);

        try {
            $this->journaliser($request, $response, $debut);
        } catch (Throwable) {
            // Le suivi ne doit jamais casser la requête de l'utilisateur.
        }

        return $response;
    }

    private function journaliser(Request $request, Response $response, float $debut): void
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return;
        }

        if (! $user->last_seen_at || $user->last_seen_at->lt(now()->subSeconds(30))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $route = $request->route();
        $nomRoute = $route?->getName();

        if ($nomRoute && Str::is(self::ROUTES_IGNOREES, $nomRoute)) {
            return;
        }

        $estAction = ! $request->isMethod('GET') && ! $request->isMethod('HEAD');
        // Visite de page = navigation Inertia ou chargement complet ; les rechargements partiels
        // (X-Inertia-Partial-Data, ex. actualisation automatique) ne sont pas des visites.
        $estVisite = $request->isMethod('GET')
            && ! $request->hasHeader('X-Inertia-Partial-Data')
            && ($request->header('X-Inertia') || ! $request->expectsJson());

        if (! $estAction && ! $estVisite) {
            return;
        }

        $auteur = $request->attributes->get('activite_user_id', $user->id);

        ActivityLog::create([
            'user_id' => $auteur,
            'impersonator_id' => $auteur === $user->id ? $request->session()->get('impersonator_id') : null,
            'method' => $request->method(),
            'route_name' => $nomRoute,
            'url' => Str::limit($request->getRequestUri(), 990, ''),
            'status' => $response->getStatusCode(),
            'parametres' => $route ? $this->parametres($route->parameters()) : null,
            'donnees' => $estAction ? $this->donnees($request) : null,
            'ip' => $request->ip(),
            'duree_ms' => (int) round((microtime(true) - $debut) * 1000),
        ]);
    }

    private function parametres(array $parametres): ?array
    {
        $resultat = [];
        foreach ($parametres as $cle => $valeur) {
            $resultat[$cle] = is_object($valeur) && method_exists($valeur, 'getKey') ? $valeur->getKey() : (is_scalar($valeur) ? $valeur : null);
        }

        return $resultat ?: null;
    }

    /** Données envoyées, sans mots de passe ni jetons, tronquées. */
    private function donnees(Request $request): ?array
    {
        $donnees = [];
        foreach ($request->except(['_token', '_method']) as $cle => $valeur) {
            if (count($donnees) >= 25) {
                $donnees['…'] = 'champs supplémentaires non affichés';
                break;
            }
            if (preg_match(self::CHAMPS_SENSIBLES, (string) $cle)) {
                $donnees[$cle] = '••••••';
                continue;
            }
            $donnees[$cle] = $this->resumer($valeur);
        }

        foreach ($request->allFiles() as $cle => $fichier) {
            $donnees[$cle] = is_array($fichier) ? count($fichier) . ' fichier(s)' : 'fichier : ' . $fichier->getClientOriginalName();
        }

        return $donnees ?: null;
    }

    private function resumer(mixed $valeur): mixed
    {
        if (is_array($valeur)) {
            return count($valeur) > 10
                ? count($valeur) . ' éléments'
                : array_map(fn ($v) => $this->resumer($v), array_slice($valeur, 0, 10, true));
        }
        if (is_string($valeur)) {
            return Str::limit($valeur, 200);
        }

        return is_scalar($valeur) || $valeur === null ? $valeur : '[objet]';
    }
}
