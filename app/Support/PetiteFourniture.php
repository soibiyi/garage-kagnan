<?php

namespace App\Support;

use App\Models\Devis;

/**
 * Petite fourniture d'un devis :
 *  - automatique : 3 % du TTC des lignes
 *  - ou montant saisi à la création du devis (petite_fourniture_montant)
 *  - petite_fourniture_active = false : aucune petite fourniture
 */
class PetiteFourniture
{
    public const TAUX = 0.03;

    /** Total TTC final (lignes + petite fourniture), arrondi au FCFA. */
    public static function totalFinal(?Devis $devis, float $brutTtc): int
    {
        if ($brutTtc <= 0) {
            return 0;
        }

        $actif = $devis === null || $devis->petite_fourniture_active === null
            ? true
            : (bool) $devis->petite_fourniture_active;

        if (!$actif) {
            return (int) round($brutTtc);
        }

        if ($devis !== null && $devis->petite_fourniture_montant !== null) {
            return (int) round($brutTtc + (float) $devis->petite_fourniture_montant);
        }

        return (int) round($brutTtc * (1 + self::TAUX));
    }

    /** Montant de la petite fourniture (écart entre total final et TTC brut). */
    public static function montant(?Devis $devis, float $brutTtc): int
    {
        return $brutTtc <= 0 ? 0 : self::totalFinal($devis, $brutTtc) - (int) round($brutTtc);
    }
}