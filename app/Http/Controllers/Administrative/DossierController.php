<?php
namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Devis;
use App\Models\LigneDevis;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DossierController extends Controller
{
    public function index()
    {
        $dossiers = Intervention::with(['vehicule.client', 'mecanicien', 'receptionniste'])
            ->where('statut', 'atelier') 
            ->latest()
            ->get();

        return Inertia::render('Administration/DossiersIndex', [
            'dossiers' => $dossiers
        ]);
    }

    public function facturationIndex()
    {
        $dossiers = Intervention::with(['vehicule.client', 'devis', 'mecanicien'])
            ->whereIn('statut', ['attente_accord', 'termine']) 
            ->latest()
            ->get();

        return Inertia::render('Administration/FacturationIndex', [
            'dossiers' => $dossiers
        ]);
    }

    // Affichage du détail de facturation d'un dossier spécifique
    public function facturationShow(Intervention $dossier)
    {
        $dossier->load(['vehicule.client', 'devis.lignes']);

        return Inertia::render('Administration/FacturationShow', [
            'dossier' => $dossier
        ]);
    }

    public function show(Intervention $dossier)
    {
        $dossier->load(['vehicule.client', 'mecanicien', 'receptionniste']);

        return Inertia::render('Administration/DossierShow', [
            'dossier' => $dossier
        ]);
    }

    public function storeDevis(Request $request, Intervention $dossier)
    {
        $request->validate([
            'lignes' => 'required|array|min:1',
            'lignes.*.quantite' => 'required|numeric|min:0',
            'lignes.*.designation' => 'required|string',
            'lignes.*.pu_net' => 'required|numeric|min:0',
            'lignes.*.remise' => 'nullable|numeric|min:0',
            'lignes.*.reference_piece' => 'nullable|string',
            'lignes.*.ne_pas_appliquer_tva' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request, $dossier) {
            // Création de l'entête du devis
            $devis = Devis::create([
                'intervention_id' => $dossier->id,
                'createur_id' => auth()->id(),
                'statut' => 'en_attente',
            ]);

            // Enregistrement des lignes du devis
            foreach ($request->lignes as $ligne) {
                $qte = $ligne['quantite'] ?? 1;
                $pu = $ligne['pu_net'] ?? 0;
                $remise = $ligne['remise'] ?? 0;
                
                $ht = ($qte * $pu) - $remise;
                $nePasAppliquerTva = $ligne['ne_pas_appliquer_tva'] ?? false;
                $ttc = $nePasAppliquerTva ? $ht : $ht * 1.18;

                LigneDevis::create([
                    'devis_id' => $devis->id,
                    'quantite' => $qte,
                    'designation' => $ligne['designation'],
                    'reference_piece' => $ligne['reference_piece'] ?? null,
                    'pu_net' => $pu,
                    'remise' => $remise,
                    'montant_ht' => $ht,
                    'montant_ttc' => $ttc,
                    'ne_pas_appliquer_tva' => $nePasAppliquerTva,
                    'type' => 'main_d_oeuvre', // Vous pouvez ajuster dynamiquement si besoin
                ]);
            }

            // Mise à jour du statut du dossier
            $dossier->update(['statut' => 'attente_accord']);
        });

        return redirect()->route('administration.dossiers.index')
            ->with('success', 'Devis enregistré avec succès.');
    }
}