<?php

namespace App\Support;

use App\Models\Parametre;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Paramètres globaux modifiables par le super administrateur (page « Système »).
 * Lecture : Parametres::get('tva') ; les valeurs par défaut s'appliquent tant que rien n'est enregistré.
 */
class Parametres
{
    private const CACHE = 'parametres.globaux';

    /** Définition des paramètres : valeur par défaut + règles de validation + libellé. */
    public const DEFINITIONS = [
        // Facturation
        'tva' => ['defaut' => 18.0, 'regles' => ['required', 'numeric', 'min:0', 'max:100'], 'groupe' => 'Facturation', 'libelle' => 'TVA (%)', 'aide' => 'Appliquée aux nouveaux devis (lignes non exonérées).'],
        // Stock
        'seuil_stock' => ['defaut' => 5, 'regles' => ['required', 'integer', 'min:0', 'max:10000'], 'groupe' => 'Stock', 'libelle' => "Seuil d'alerte stock", 'aide' => 'Une pièce passe en réapprovisionnement à ce niveau ou en dessous.'],
        // Contrôle
        'alerte_remise_pct' => ['defaut' => 20.0, 'regles' => ['required', 'numeric', 'min:0', 'max:100'], 'groupe' => 'Contrôle', 'libelle' => 'Alerte remise à partir de (%)', 'aide' => 'Une ligne de devis avec une remise égale ou supérieure déclenche une alerte.'],
        'alerte_mvt_stock_qte' => ['defaut' => 20, 'regles' => ['required', 'integer', 'min:1', 'max:100000'], 'groupe' => 'Contrôle', 'libelle' => 'Alerte mouvement de stock à partir de (pièces)', 'aide' => 'Un mouvement manuel de cette quantité ou plus déclenche une alerte.'],
        // Sécurité
        'heure_ouverture' => ['defaut' => '06:00', 'regles' => ['required', 'date_format:H:i'], 'groupe' => 'Sécurité', 'libelle' => 'Début des heures habituelles', 'aide' => 'Une connexion en dehors de la plage est signalée.'],
        'heure_fermeture' => ['defaut' => '21:00', 'regles' => ['required', 'date_format:H:i'], 'groupe' => 'Sécurité', 'libelle' => 'Fin des heures habituelles', 'aide' => null],
        // Rapports
        'rapport_quotidien' => ['defaut' => true, 'regles' => ['required', 'boolean'], 'groupe' => 'Rapports', 'libelle' => 'Rapport quotidien (chaque soir à 20 h)', 'aide' => null],
        'rapport_hebdomadaire' => ['defaut' => true, 'regles' => ['required', 'boolean'], 'groupe' => 'Rapports', 'libelle' => 'Rapport hebdomadaire (lundi 7 h)', 'aide' => null],
        'rapport_email' => ['defaut' => true, 'regles' => ['required', 'boolean'], 'groupe' => 'Rapports', 'libelle' => 'Envoyer aussi les rapports par e-mail', 'aide' => null],
        // Maintenance (géré depuis son propre bloc)
        'maintenance_active' => ['defaut' => false, 'regles' => ['required', 'boolean'], 'groupe' => null, 'libelle' => 'Mode maintenance', 'aide' => null],
        'maintenance_message' => ['defaut' => "L'application est en maintenance. Merci de réessayer dans quelques minutes.", 'regles' => ['nullable', 'string', 'max:500'], 'groupe' => null, 'libelle' => 'Message de maintenance', 'aide' => null],
    ];

    public static function get(string $cle): mixed
    {
        return self::tous()[$cle] ?? self::DEFINITIONS[$cle]['defaut'] ?? null;
    }

    /** Toutes les valeurs (défauts + valeurs enregistrées), en cache. */
    public static function tous(): array
    {
        $defauts = array_map(fn ($d) => $d['defaut'], self::DEFINITIONS);

        try {
            $enregistres = Cache::rememberForever(self::CACHE, fn () => Schema::hasTable('parametres')
                ? Parametre::query()->pluck('valeur', 'cle')->all()
                : []);
        } catch (Throwable) {
            $enregistres = [];
        }

        return array_merge($defauts, array_intersect_key($enregistres, $defauts));
    }

    public static function set(array $valeurs, ?int $userId = null): void
    {
        foreach ($valeurs as $cle => $valeur) {
            if (! array_key_exists($cle, self::DEFINITIONS)) {
                continue;
            }
            Parametre::updateOrCreate(['cle' => $cle], ['valeur' => $valeur, 'modifie_par' => $userId]);
        }
        Cache::forget(self::CACHE);
    }

    public static function tauxTva(): float
    {
        return (float) self::get('tva') / 100;
    }

    /** L'heure donnée est-elle en dehors de la plage habituelle ? */
    public static function horsHoraires(?\DateTimeInterface $moment = null): bool
    {
        $heure = ($moment ?? now())->format('H:i');
        $debut = (string) self::get('heure_ouverture');
        $fin = (string) self::get('heure_fermeture');

        // Plage qui passe minuit (ex. 22:00 → 06:00)
        return $debut <= $fin ? ($heure < $debut || $heure > $fin) : ($heure < $debut && $heure > $fin);
    }
}
