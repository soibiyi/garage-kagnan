<?php

namespace App\Models\Concerns;

use App\Support\Audit;

/**
 * Trace chaque création, modification et suppression du modèle (valeurs avant / après)
 * et signale les actions sensibles au super administrateur. Voir App\Support\Audit.
 *
 * Attention : les opérations en masse (Model::where(...)->update/delete) ne déclenchent
 * pas d'événement Eloquent et ne sont donc pas tracées.
 *
 * Un modèle peut limiter les événements tracés avec une méthode evenementsAudites(): array
 * (ex. ['supprime'] pour ne garder que les suppressions).
 */
trait Audite
{
    public static function bootAudite(): void
    {
        static::created(fn ($modele) => Audit::enregistrer($modele, 'cree'));
        static::updated(fn ($modele) => Audit::enregistrer($modele, 'modifie'));
        static::deleted(fn ($modele) => Audit::enregistrer($modele, 'supprime'));
    }
}
