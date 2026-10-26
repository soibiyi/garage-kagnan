<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\Paiement;
use App\Support\PetiteFourniture;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FactureController extends Controller
{
    /** TTC brut (sans petite fourniture) des lignes acceptées du devis. */
    private function brutAccepte(Intervention $dossier): float
    {
        if (!$dossier->devis) {
            return 0;
        }

        return (float) $dossier->devis->lignes->where('is_accepted', true)->sum('montant_ttc');
    }

    /** Total TTC (avec petite fourniture auto 3 % ou saisie, arrondi au FCFA) des lignes acceptées. */
    private function totalAccepte(Intervention $dossier): int
    {
        if (!$dossier->devis) {
            return 0;
        }

        return PetiteFourniture::totalFinal($dossier->devis, $this->brutAccepte($dossier));
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
            'petite_fourniture' => $dossier->devis
                ? PetiteFourniture::montant($dossier->devis, $this->brutAccepte($dossier))
                : 0,
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

        $dossiers = Intervention::duSiege()->with(['vehicule.client', 'devis.lignes', 'mecanicien'])
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

        $factures = Facture::whereIn('intervention_id', $dossiers->pluck('id'))
            ->get()
            ->keyBy('intervention_id');

        // Factures ayant déjà au moins un versement : leur devis n'est plus modifiable
        $facturesAvecPaiement = Paiement::whereIn('facture_id', $factures->pluck('id'))
            ->pluck('facture_id')
            ->unique();

        $dossiers->each(function ($d) use ($factures, $facturesAvecPaiement) {
            $facture = $factures->get($d->id);
            $paye = (int) round((float) optional($facture)->montant_paye);
            $d->setAttribute('resume_paiement', $this->resume($d, $paye));
            $d->setAttribute('a_paiement', $facture !== null && $facturesAvecPaiement->contains($facture->id));
        });

        return Inertia::render('Administration/Factures/Index', [
            'dossiers' => $dossiers,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Exportation native au format CSV compatible Excel respectant le modèle de créance.
     */
    public function export(Request $request): StreamedResponse
    {
        $fileName = 'tableau_de_creance_' . date('Y-m-d') . '.csv';

        $dossiers = Intervention::duSiege()->with(['vehicule.client', 'devis.lignes'])
            ->where('statut', 'accepte')
            ->latest()
            ->get();

        $factures = Facture::whereIn('intervention_id', $dossiers->pluck('id'))
            ->get()
            ->keyBy('intervention_id');

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($dossiers, $factures) {
            $file = fopen('php://output', 'w');
            // Ajout du BOM UTF-8 pour la reconnaissance correcte des caractères spéciaux dans Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // En-têtes conformes au modèle fourni
            fputcsv($file, [
                "Numéro Facture",
                "Immatriculation",
                "Client",
                "Date facture",
                "CHIFFRE D'AFFAIRE HORS REMISE TTC",
                "REMISE",
                "CHIFFRE D'AFFAIRE NET TTC",
                "ENCAISSEMENT",
                "CREANCE",
                "MODE DE PAIEMENT"
            ], ";");

            foreach ($dossiers as $dossier) {
                $facture = $factures->get($dossier->id);
                $paye = $facture ? (int) round((float) $facture->montant_paye) : 0;
                $resume = $this->resume($dossier, $paye);

                $numeroFacture = $resume['numero'] ?? ($dossier->numero_ot ?? ('#' . $dossier->id));
                $immatriculation = $dossier->vehicule?->immatriculation ?? '';
                
                $clientNom = $dossier->vehicule?->client?->nom ?? '';
                $clientPrenom = $dossier->vehicule?->client?->prenom ?? $dossier->vehicule?->client?->name ?? '';
                $client = trim("{$clientNom} {$clientPrenom}") ?: 'N/A';

                $dateFacture = optional($dossier->created_at)->format('d/m/Y') ?? '';
                $totalTtc = $resume['total_ttc'];
                $remise = 0; // Ajustable selon votre gestion des remises
                $netTtc = $totalTtc - $remise;
                $encaissement = $resume['montant_paye'];
                $creance = $resume['reste'];

                // Récupération du mode de paiement du dernier versement ou par défaut
                $dernierPaiement = $facture ? Paiement::where('facture_id', $facture->id)->latest('date_paiement')->first() : null;
                $modePaiement = $dernierPaiement?->mode_paiement ?? 'ESPECES';

                fputcsv($file, [
                    $numeroFacture,
                    $immatriculation,
                    $client,
                    $dateFacture,
                    $totalTtc,
                    $remise > 0 ? $remise : '',
                    $netTtc,
                    $encaissement,
                    $creance,
                    $modePaiement
                ], ";");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Détail d'un dossier + historique des versements.
     */
    public function show($id)
    {
        $dossier = Intervention::duSiege()->with(['vehicule.client', 'devis.lignes'])->findOrFail($id);

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
            $dossier = Intervention::duSiege()->lockForUpdate()->findOrFail($id);
            $dossier->load(['devis.lignes']);

            $total = $this->totalAccepte($dossier);

            if ($total <= 0) {
                $erreur = "Aucune ligne acceptée à facturer pour ce dossier.";
                return;
            }

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
                'date_paiement'  => Carbon::parse($request->date_paiement)->setTimeFrom(now()),
            ])->save();

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

    /**
     * Supprime un devis direct.
     */
    public function destroy($id)
    {
        $dossier = Intervention::duSiege()->findOrFail($id);

        $isDirect = $dossier->type === 'devis_direct' || $dossier->is_direct || empty($dossier->numero_ot);

        if (!$isDirect) {
            return back()->withErrors(['error' => 'Seuls les devis directs peuvent être supprimés.']);
        }

        DB::transaction(function () use ($dossier) {
            if ($facture = Facture::where('intervention_id', $dossier->id)->first()) {
                Paiement::where('facture_id', $facture->id)->delete();
                $facture->delete();
            }

            if ($dossier->devis) {
                $dossier->devis->lignes()->delete();
                $dossier->devis->delete();
            }

            $dossier->delete();
        });

        return back()->with('success', 'Le devis direct a été supprimé avec succès.');
    }
}