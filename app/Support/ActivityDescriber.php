<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

/** Traduit une entrée du journal d'activité en phrase lisible pour le super administrateur. */
class ActivityDescriber
{
    /** Espace de l'application, selon le préfixe du nom de route (le premier qui correspond gagne). */
    private const ESPACES = [
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'dashboard' => 'Tableau de bord',
        'profile.*' => 'Profil',
        'superadmin.erreurs.*' => 'Erreurs de l’application',
        'superadmin.petites-fournitures' => 'Petites fournitures',
        'superadmin.*' => 'Supervision',
        'admin.users.*' => 'Gestion des utilisateurs',
        'admin.secretariat.*' => 'Secrétariat',
        'admin.clients.*' => 'Clients',
        'admin.charge_clients.*' => 'Chargés client',
        'admin.missions.*' => 'Missions',
        'admin.chauffeurs.*' => 'Chauffeurs',
        'admin.vehicules.*' => 'Véhicules',
        'admin.chiffre-affaires' => "Chiffre d'affaires",
        'admin.pieces.*' => 'Pièces',
        'admin.stocks.*' => 'Stock',
        'admin.siege.*' => 'Changement de siège',
        'admin.*' => 'Administration',
        'reception.*' => 'Réception véhicule',
        'parc.*' => 'Parc véhicules',
        'mecanicien.*' => 'Atelier',
        'administration.dossiers.*' => 'Dossiers',
        'administration.devis*' => 'Devis',
        'administration.facturation.*' => 'Facturation',
        'administration.factures.*' => 'Factures',
        'administration.stocks.*' => 'Stock',
        'administration.chiffre-affaires' => "Chiffre d'affaires",
        'administration.*' => 'Administration',
        'magasinier.*' => 'Magasin',
        'charge_client.*' => 'Suivi client',
        'chauffeur.*' => 'Missions chauffeur',
        'depanneur.*' => 'Missions dépannage',
        'secretaire.*' => 'Secrétariat',
        'secretariat.*' => 'Secrétariat',
    ];

    /** Dernier segment du nom de route → action. */
    private const ACTIONS = [
        'store' => 'a enregistré',
        'update' => 'a modifié',
        'destroy' => 'a supprimé',
        'toggle' => 'a coché / décoché',
        'statut' => 'a changé le statut',
        'accept' => 'a accepté',
        'decline' => 'a refusé',
        'refuse' => 'a refusé',
        'start' => 'a démarré',
        'complete' => 'a terminé',
        'progress' => 'a fait avancer',
        'encaisser' => 'a encaissé',
        'import' => 'a importé',
        'export' => 'a exporté',
        'switch' => 'a changé',
        'read' => 'a marqué comme lu',
        'annuler' => 'a annulé',
    ];

    public static function espace(?string $route): string
    {
        if (! $route) {
            return 'Page';
        }
        foreach (self::ESPACES as $motif => $libelle) {
            if (Str::is($motif, $route)) {
                return $libelle;
            }
        }

        return Str::headline(Str::before($route, '.'));
    }

    public static function decrire(ActivityLog $log): string
    {
        $espace = self::espace($log->route_name);

        if ($log->method === 'POST' && Str::startsWith($log->url, '/login')) {
            return 'Connexion à l’application';
        }
        if ($log->method === 'GET') {
            return "Consulte : {$espace}";
        }

        $dernier = Str::afterLast((string) $log->route_name, '.');
        foreach (self::ACTIONS as $cle => $verbe) {
            if (Str::contains($dernier, $cle)) {
                return ucfirst($verbe) . " · {$espace}";
            }
        }

        return match ($log->method) {
            'POST' => "A envoyé un formulaire · {$espace}",
            'PUT', 'PATCH' => "A modifié · {$espace}",
            'DELETE' => "A supprimé · {$espace}",
            default => $espace,
        };
    }

    public static function presenter(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'description' => self::decrire($log),
            'espace' => self::espace($log->route_name),
            'method' => $log->method,
            'route_name' => $log->route_name,
            'url' => $log->url,
            'status' => $log->status,
            'parametres' => $log->parametres,
            'donnees' => $log->donnees,
            'ip' => $log->ip,
            'duree_ms' => $log->duree_ms,
            'created_at' => $log->created_at,
            'user' => $log->relationLoaded('user') && $log->user
                ? ['id' => $log->user->id, 'name' => $log->user->name, 'role' => $log->user->role_affichage]
                : null,
            'impersonator' => $log->relationLoaded('impersonator') && $log->impersonator
                ? ['id' => $log->impersonator->id, 'name' => $log->impersonator->name]
                : null,
        ];
    }
}
