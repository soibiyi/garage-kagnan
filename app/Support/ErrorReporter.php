<?php

namespace App\Support;

use App\Models\ErrorLog;
use App\Models\User;
use App\Notifications\ErreurApplication;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Enregistre les erreurs de l'application et prévient immédiatement les super administrateurs.
 * Les erreurs identiques (même type, fichier, ligne) sont regroupées : on incrémente un compteur
 * au lieu d'inonder le journal et les notifications.
 */
class ErrorReporter
{
    private static bool $enCours = false;

    public static function capturerException(Throwable $e): void
    {
        self::enregistrer([
            'source' => 'serveur',
            'type' => $e::class,
            'message' => $e->getMessage() ?: '(sans message)',
            'fichier' => self::cheminRelatif($e->getFile()),
            'ligne' => $e->getLine(),
            'trace' => Str::limit($e->getTraceAsString(), 20000),
        ]);
    }

    /** Erreur JavaScript remontée par le navigateur d'un utilisateur. */
    public static function capturerClient(array $data): void
    {
        self::enregistrer([
            'source' => 'client',
            'type' => Str::limit($data['type'] ?? 'Erreur JavaScript', 250, ''),
            'message' => Str::limit($data['message'] ?? '(sans message)', 2000),
            'fichier' => isset($data['fichier']) ? Str::limit($data['fichier'], 490, '') : null,
            'ligne' => isset($data['ligne']) ? (int) $data['ligne'] : null,
            'trace' => isset($data['trace']) ? Str::limit($data['trace'], 20000) : null,
            'url_client' => $data['url'] ?? null,
        ]);
    }

    private static function enregistrer(array $erreur): void
    {
        // Ne jamais laisser la capture d'une erreur en provoquer une autre (base indisponible, boucle…)
        if (self::$enCours) {
            return;
        }
        self::$enCours = true;

        try {
            // Contexte HTTP seulement pour une vraie requête (pas pour artisan / les tâches planifiées)
            $request = request();
            if (! $request->route() && app()->runningInConsole()) {
                $request = null;
            }
            $url = $erreur['url_client'] ?? $request?->fullUrl();
            unset($erreur['url_client']);

            $fingerprint = hash('sha256', implode('|', [
                $erreur['source'],
                $erreur['type'],
                $erreur['fichier'] ?? '',
                $erreur['ligne'] ?? '',
                // Pour les erreurs JS sans fichier, le message sert d'empreinte
                $erreur['fichier'] ? '' : Str::limit($erreur['message'], 200, ''),
            ]));

            $existante = ErrorLog::query()
                ->where('fingerprint', $fingerprint)
                ->whereNull('resolue_at')
                ->first();

            if ($existante) {
                $existante->increment('occurrences', 1, [
                    'derniere_fois' => now(),
                    'message' => $erreur['message'],
                    'url' => Str::limit((string) $url, 990, ''),
                    'user_id' => self::collaborateurId($request) ?? $existante->user_id,
                ]);

                return;
            }

            $log = ErrorLog::create([
                ...$erreur,
                'fingerprint' => $fingerprint,
                'url' => $url ? Str::limit($url, 990, '') : null,
                'method' => $request?->method(),
                'user_id' => self::collaborateurId($request),
                'premiere_fois' => now(),
                'derniere_fois' => now(),
            ]);

            $superAdmins = User::query()->where('role', 'admin')->where('is_superadmin', true)->get();
            if ($superAdmins->isNotEmpty()) {
                Notification::send($superAdmins, new ErreurApplication($log));
            }
        } catch (Throwable) {
            // Silence volontaire : le journal Laravel standard garde la trace de l'erreur d'origine.
        } finally {
            self::$enCours = false;
        }
    }

    /** Collaborateur connecté (les clients du portail ne sont pas dans la table users). */
    private static function collaborateurId($request): ?int
    {
        $user = $request?->user();

        return $user instanceof User ? $user->id : null;
    }

    private static function cheminRelatif(string $chemin): string
    {
        return Str::after(str_replace('\\', '/', $chemin), str_replace('\\', '/', base_path()) . '/');
    }
}
