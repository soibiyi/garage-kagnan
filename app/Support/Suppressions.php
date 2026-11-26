<?php

namespace App\Support;

use App\Models\DemandeSuppression;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Règle de suppression des dossiers et devis directs :
 *  - administrateur (et super administrateur) : suppression immédiate ;
 *  - autres collaborateurs : demande de suppression, avec motif, à valider par un administrateur.
 *
 * Dans tous les cas, la suppression est tracée élément par élément (journal d'audit) et le
 * super administrateur peut la restaurer. Les photos restent sur le disque pour que la
 * restauration soit complète.
 */
class Suppressions
{
    public static function estAdmin(?User $user): bool
    {
        return $user?->role === 'admin';
    }

    /** Suppression directe (admin) ou demande (autres). Réponse prête à renvoyer par le contrôleur. */
    public static function traiter(string $type, Intervention $dossier, ?string $motif, User $user): RedirectResponse
    {
        if (self::estAdmin($user)) {
            self::executer($type, $dossier);

            return back()->with('success', self::TYPES_SUPPRIMES[$type] . ' ' . self::nom($dossier) . ' a été supprimé.');
        }

        $motif = trim((string) $motif);
        if (mb_strlen($motif) < 5) {
            return back()->withErrors(['motif' => 'Indiquez le motif de la suppression (au moins 5 caractères).']);
        }

        $existante = DemandeSuppression::enAttente()->where('type', $type)->where('cible_id', $dossier->id)->exists();
        if ($existante) {
            return back()->with('success', "Une demande de suppression est déjà en attente pour cet élément. L'administrateur va la traiter.");
        }

        DemandeSuppression::create([
            'type' => $type,
            'cible_id' => $dossier->id,
            'libelle' => DemandeSuppression::TYPES[$type] . ' ' . self::nom($dossier),
            'contexte' => self::contexte($dossier),
            'motif' => mb_substr($motif, 0, 2000),
            'siege' => $dossier->siege,
            'demandeur_id' => $user->id,
        ]);

        return back()->with('success', "Demande de suppression envoyée à l'administrateur. Le dossier reste en place tant qu'elle n'est pas validée.");
    }

    private const TYPES_SUPPRIMES = [
        'dossier' => 'Le dossier',
        'devis_direct' => 'Le devis direct',
        'facturation' => 'Le dossier de facturation',
    ];

    /** Supprime réellement (dossier + devis + lignes + facture + paiements), dans une transaction. */
    public static function executer(string $type, Intervention $dossier): void
    {
        if (!array_key_exists($type, self::TYPES_SUPPRIMES)) {
            throw new RuntimeException('Ce type de suppression n’est pas pris en charge.');
        }

        if ($type === 'devis_direct' && ! self::estDevisDirect($dossier)) {
            throw new RuntimeException('Seuls les devis directs peuvent être supprimés depuis la facturation.');
        }

        DB::transaction(fn () => $dossier->supprimerAvecDependances());
    }

    public static function estDevisDirect(Intervention $dossier): bool
    {
        return $dossier->type === 'devis_direct' || $dossier->is_direct || empty($dossier->numero_ot);
    }

    private static function nom(Intervention $dossier): string
    {
        return $dossier->numero_ot ?: '#' . $dossier->id;
    }

    /** Ce que l'administrateur doit voir pour décider (figé au moment de la demande). */
    private static function contexte(Intervention $dossier): array
    {
        $facture = Facture::where('intervention_id', $dossier->id)->first(['numero_facture', 'montant_total', 'montant_paye']);

        return array_filter(Audit::contexte($dossier) + [
            'statut' => $dossier->statut,
            'facture' => $facture?->numero_facture,
            'montant_total' => $facture ? (float) $facture->montant_total : null,
            'montant_paye' => $facture ? (float) $facture->montant_paye : null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
