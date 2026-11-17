<?php

namespace App\Support;

use App\Models\Facture;
use App\Models\Intervention;
use App\Models\Paiement;

/**
 * Total facturé d'un dossier et synchronisation de la facture stockée.
 *
 * Le total est toujours recalculé à partir des lignes acceptées (devis principal
 * + devis complémentaires, petite fourniture comprise) ; la table « factures »
 * n'en garde qu'une copie, à remettre à jour après chaque modification du devis.
 */
class TotauxFacture
{
    /** Total TTC (petite fourniture comprise, arrondi au FCFA) des lignes acceptées. */
    public static function totalAccepte(Intervention $dossier): int
    {
        $dossier->loadMissing(['devis.lignes', 'devisComplements.lignes']);

        return collect([$dossier->devis])->filter()->concat($dossier->devisComplements)
            ->sum(function ($devis) {
                $brut = (float) $devis->lignes->where('is_accepted', true)->sum('montant_ttc');

                return PetiteFourniture::totalFinal($devis, $brut);
            });
    }

    /** Somme des versements déjà enregistrés sur la facture du dossier. */
    public static function encaisse(Intervention $dossier): int
    {
        return (int) round((float) Paiement::whereIn(
            'facture_id',
            Facture::where('intervention_id', $dossier->id)->select('id')
        )->sum('montant'));
    }

    public static function statut(int $total, int $paye): string
    {
        if ($total > 0 && $paye >= $total) {
            return 'payee';
        }

        return $paye > 0 ? 'partiellement_payee' : 'impayee';
    }

    /** Remet le montant total et le statut de la facture en phase avec le devis modifié. */
    public static function resynchroniser(Intervention $dossier): void
    {
        $facture = Facture::where('intervention_id', $dossier->id)->first();
        if (! $facture) {
            return;
        }

        $dossier->unsetRelation('devis')->unsetRelation('devisComplements');
        $total = self::totalAccepte($dossier);
        $paye = self::encaisse($dossier);

        $facture->forceFill([
            'montant_total' => $total,
            'montant_paye' => $paye,
            'statut' => self::statut($total, $paye),
        ])->save();
    }
}
