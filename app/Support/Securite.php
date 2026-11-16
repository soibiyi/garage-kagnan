<?php

namespace App\Support;

use App\Models\Connexion;
use App\Models\User;
use App\Notifications\ActionSensible;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sécurité des comptes collaborateurs : historique des connexions, alertes
 * (connexion hors horaires, échecs répétés), déconnexion forcée et suspension.
 */
class Securite
{
    /** Nombre d'échecs en 15 minutes sur un même e-mail avant alerte. */
    private const ECHECS_AVANT_ALERTE = 5;

    public static function surConnexion(Login $event): void
    {
        // « Voir en tant que » : ce n'est pas une vraie connexion du collaborateur
        if (! $event->user instanceof User || session()->has('impersonator_id')) {
            return;
        }

        try {
            $horsHoraires = Parametres::horsHoraires();
            Connexion::create([
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'reussie' => true,
                'hors_horaires' => $horsHoraires,
                'ip' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 490, ''),
            ]);

            if ($horsHoraires && ! $event->user->estSuperAdmin()) {
                Audit::prevenir(new ActionSensible('securite', "Connexion hors des heures habituelles : {$event->user->name} à " . now()->format('H:i'), [
                    'user_id' => $event->user->id,
                    'auteur' => $event->user->name,
                ]));
            }
        } catch (Throwable) {
            // Ne jamais bloquer une connexion à cause de l'historique
        }
    }

    public static function surEchec(Failed $event): void
    {
        // Seuls les collaborateurs (le portail client a son propre garde)
        if ($event->guard !== 'web') {
            return;
        }

        try {
            $email = Str::limit((string) ($event->credentials['email'] ?? ''), 190, '');
            $user = $event->user instanceof User ? $event->user : null;

            Connexion::create([
                'user_id' => $user?->id,
                'email' => $email,
                'reussie' => false,
                'motif' => match (true) {
                    ! $user => 'Adresse inconnue',
                    (bool) $user->suspendu_at => 'Compte suspendu',
                    default => 'Mot de passe incorrect',
                },
                'hors_horaires' => Parametres::horsHoraires(),
                'ip' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 490, ''),
            ]);

            // Alerte une seule fois quand le seuil est atteint (pas à chaque échec suivant)
            $echecs = Connexion::query()
                ->where('email', $email)
                ->where('reussie', false)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();
            if ($echecs === self::ECHECS_AVANT_ALERTE) {
                Audit::prevenir(new ActionSensible('securite', "{$echecs} échecs de connexion en 15 min pour {$email}", [
                    'user_id' => $user?->id,
                    'auteur' => $user?->name ?? $email,
                ]));
            }
        } catch (Throwable) {
            // idem
        }
    }

    /** Ferme toutes les sessions ouvertes d'un collaborateur (sur tous ses appareils). */
    public static function deconnecterPartout(User $user): int
    {
        // Empêche la reconnexion automatique par le cookie « se souvenir de moi »
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        return DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    /** Navigateur / appareil lisible à partir du user agent. */
    public static function appareil(?string $ua): string
    {
        if (! $ua) {
            return 'Inconnu';
        }
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Autre',
        };
        $nav = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Navigateur',
        };

        return "{$nav} · {$os}";
    }
}
