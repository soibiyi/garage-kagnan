<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FactureController extends Controller
{
    /** Total TTC (arrondi au FCFA) des lignes acceptées du devis. */
    private function totalAccepte(Intervention $dossier): int
    {
        if (!$dossier->devis) {
            return 0;
        }

        return (int) round($dossier->devis->lignes->where('is_accepted', true)->sum('montant_ttc'));
    }

    /** Numéro de facture, même format que celui affiché sur la vue. */
    private function numeroFacture(Intervention $dossier): ?string
    {
        if (!$dossier->devis) {
            return null;
        }

        return 'F/1/ADMI/' . $dossier->devis->id . '/' . $dossier->devis->created_at->format('Y');
    }

    /** Résumé de paiement : total facturé, payé, reste, %, soldée. */
    private function resume(Intervention $dossier, int $paye): array
    {
        $total = $this->totalAccepte($dossier);
        $paye = max($paye, 0);

        return [
            'numero'       => $this->numeroFacture($dossier),
            'total_ttc'    => $total,
            'montant_paye' => $paye,
            'reste'        => max($total - $paye, 0),
            'pourcentage'  => $total > 0 ? min(100, (int) round($paye * 100 / $total)) : 0,
            'soldee'       => $total > 0 && $paye >= $total,
        ];
    }

    /** Statut stocké dans la table factures. */
    private function statutFacture(int $total, int $paye): string
    {
        if ($total > 0 && $paye >= $total) {
            return 'payee';
        }

        return $paye > 0 ? 'partiellement_payee' : 'impayee';
    }

    /**
     * Liste des dossiers dont le devis est accepté, avec l'avancement du paiement.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $dossiers = Intervention::with(['vehicule.client', 'devis.lignes', 'mecanicien'])
            ->where('statut', 'accepte')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero_ot', 'like', "%{$search}%")
                      ->orWhereHas('vehicule.client', function ($subQuery) use ($search) {
                          $subQuery->where('nom', 'like', "%{$search}%")
                                   ->orWhere('prenom', 'like', "%{$search}%");
                      });
                });
            })
            ->latest()
            ->get();

        // Une seule requête pour toutes les factures existantes (montant_paye est tenu à jour à chaque versement)
        $factures = Facture::whereIn('intervention_id', $dossiers->pluck('id'))
            ->get()
            ->keyBy('intervention_id');

        $dossiers->each(function ($d) use ($factures) {
            $paye = (int) round((float) optional($factures->get($d->id))->montant_paye);
            $d->setAttribute('resume_paiement', $this->resume($d, $paye));
        });

        return Inertia::render('Administration/Factures/Index', [
            'dossiers' => $dossiers,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Détail d'un dossier + historique des versements.
     */
    public function show($id)
    {
        $dossier = Intervention::with(['vehicule.client', 'devis.lignes'])->findOrFail($id);

        $facture = Facture::where('intervention_id', $dossier->id)->first();

        $paiements = $facture
            ? Paiement::with('enregistrePar:id,name')
                ->where('facture_id', $facture->id)
                ->orderBy('date_paiement')
                ->orderBy('id')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'date_paiement' => $p->date_paiement,
                    'montant' => (int) round((float) $p->montant),
                    'mode_paiement' => $p->mode_paiement,
                    'notes' => $p->notes,
                    'enregistre_par_nom' => optional($p->enregistrePar)->name,
                ])
            : collect();

        return Inertia::render('Administration/Factures/Show', [
            'dossier'   => $dossier,
            'paiements' => $paiements,
            'resume'    => $this->resume($dossier, (int) $paiements->sum('montant')),
        ]);
    }

    /**
     * Enregistre un versement. La facture est créée au premier versement.
     * Refuse si la facture est soldée ou si le montant dépasse le reste à payer.
     */
    public function storeEncaissement(Request $request, $id)
    {
        $request->validate([
            'montant' => 'required|integer|min:1',
            'mode_paiement' => 'required|string|in:Especes,Carte Bancaire,Virement,Orange Money,MTN Money,Moov Money',
            'notes' => 'nullable|string|max:255',
            'date_paiement' => 'required|date|before_or_equal:today',
        ]);

        $erreur = null;

        DB::transaction(function () use ($request, $id, &$erreur) {
            // Verrou sur le dossier : évite deux encaissements simultanés qui dépasseraient le total
            $dossier = Intervention::lockForUpdate()->findOrFail($id);
            $dossier->load(['devis.lignes']);

            $total = $this->totalAccepte($dossier);

            if ($total <= 0) {
                $erreur = "Aucune ligne acceptée à facturer pour ce dossier.";
                return;
            }

            // Facture du dossier : créée au premier versement
            $facture = Facture::where('intervention_id', $dossier->id)->first();

            if (!$facture) {
                $facture = (new Facture)->forceFill([
                    'intervention_id' => $dossier->id,
                    'devis_id'        => $dossier->devis->id,
                    'numero_facture'  => $this->numeroFacture($dossier),
                    'montant_total'   => $total,
                    'montant_paye'    => 0,
                    'statut'          => 'impayee',
                ]);
                $facture->save();
            }

            $dejaPaye = (int) round((float) Paiement::where('facture_id', $facture->id)->sum('montant'));
            $reste = max($total - $dejaPaye, 0);

            if ($reste <= 0) {
                $erreur = "Cette facture est déjà soldée.";
                return;
            }
            if ((int) $request->montant > $reste) {
                $erreur = "Le montant dépasse le reste à payer (" . number_format($reste, 0, ',', ' ') . " F).";
                return;
            }

            (new Paiement)->forceFill([
                'facture_id'     => $facture->id,
                'enregistre_par' => auth()->id(),
                'montant'        => (int) $request->montant,
                'mode_paiement'  => $request->mode_paiement,
                'notes'          => $request->notes,
                // Date choisie + heure actuelle
                'date_paiement'  => Carbon::parse($request->date_paiement)->setTimeFrom(now()),
            ])->save();

            // Mise à jour de la facture : total, montant payé et statut
            $nouveauPaye = $dejaPaye + (int) $request->montant;

            $facture->forceFill([
                'montant_total' => $total,
                'montant_paye'  => $nouveauPaye,
                'statut'        => $this->statutFacture($total, $nouveauPaye),
            ])->save();
        });

        if ($erreur) {
            return back()->withErrors(['montant' => $erreur]);
        }

        return back()->with('success', 'Versement enregistré avec succès.');
    }
}