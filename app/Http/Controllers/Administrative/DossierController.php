<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Devis;
use App\Models\LigneDevis;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Stock;

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
        // On ne garde que les dossiers qui sont en attente d'accord client
        $dossiers = Intervention::with(['vehicule.client', 'devis', 'mecanicien'])
            ->where('statut', 'attente_accord') 
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

    public function updateDevisValidation(Request $request, Intervention $dossier)
    {
        $request->validate([
            'lignes_acceptees' => 'array',
            'lignes_acceptees.*' => 'exists:lignes_devis,id',
        ]);

        DB::transaction(function () use ($request, $dossier) {
            $devis = $dossier->devis;

            if ($devis) {
                $idsAcceptes = $request->input('lignes_acceptees', []);

                foreach ($devis->lignes as $ligne) {
                    $ligne->update([
                        'is_accepted' => in_array($ligne->id, $idsAcceptes)
                    ]);
                }

                // Le devis passe en statut accepté
                $devis->update(['statut' => 'accepte']);
            }

            // LE DOSSIER QUITTE LA PAGE FACTURATIONINDEX : 
            // On change son statut pour l'aiguiller vers la vue des devis validés
            $dossier->update(['statut' => 'accepte']);
        });

        // Redirection vers la liste d'attente (le dossier n'y apparaîtra plus)
        return redirect()->route('administration.facturation.index')
            ->with('success', 'Choix du client enregistré avec succès. Le dossier a basculé dans les devis validés.');
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
                    'type' => !empty($ligne['reference_piece']) ? 'piece' : 'main_d_oeuvre',
                ]);
            }

            // Mise à jour du statut du dossier
            $dossier->update(['statut' => 'attente_accord']);
        });

        return redirect()->route('administration.dossiers.index')
            ->with('success', 'Devis enregistré avec succès.');
    }

    // NOUVELLE MÉTHODE : Vue globale de tous les devis pour l'administration
    public function devisIndex()
    {
        $dossiers = Intervention::with(['vehicule.client', 'devis.lignes', 'mecanicien', 'receptionniste'])
            ->has('devis')
            ->latest()
            ->get();

        return Inertia::render('Administration/DevisIndex', [
            'dossiers' => $dossiers
        ]);
    }

    // NOUVELLE VUE : Devis validés par le client
    public function devisAcceptesIndex()
    {
        $dossiers = Intervention::with(['vehicule.client', 'devis.lignes', 'mecanicien'])
            ->where('statut', 'accepte')
            ->latest()
            ->get();

        return Inertia::render('Administration/DevisAcceptesIndex', [
            'dossiers' => $dossiers
        ]);
    }

    // NOUVELLE VUE : Historique global de tous les devis (acceptés et refusés)
    public function devisHistoriqueIndex()
    {
        $dossiers = Intervention::with(['vehicule.client', 'devis.lignes', 'mecanicien'])
            ->has('devis')
            ->latest()
            ->get();

        return Inertia::render('Administration/DevisHistoriqueIndex', [
            'dossiers' => $dossiers
        ]);
    }

    // Affiche la liste des devis directs
    public function devisDirectIndex()
    {
        $devisDirects = Intervention::with(['vehicule.client', 'devis.lignes', 'receptionniste'])
            ->where('circuit', 'devis_direct')
            ->latest()
            ->get();

        return Inertia::render('Administration/DevisDirectIndex', [
            'devisDirects' => $devisDirects
        ]);
    }

    // Affiche le formulaire de création d'un devis direct
    public function devisDirectCreate()
    {
        $vehicules = \App\Models\Vehicule::with('client')->latest()->get();

        return Inertia::render('Administration/DevisDirectCreate', [
            'vehicules' => $vehicules
        ]);
    }

    public function storeDevisDirect(Request $request)
    {
        $request->validate([
            'vehicule_id' => 'nullable|exists:vehicules,id',
            'nouveau_client_nom' => 'required_without:vehicule_id|nullable|string|max:255',
            'nouveau_client_prenom' => 'nullable|string|max:255',
            'nouvelle_marque' => 'required_without:vehicule_id|nullable|string|max:255',
            'nouveau_modele' => 'required_without:vehicule_id|nullable|string|max:255',
            'remarques' => 'nullable|string',
            'lignes' => 'required|array|min:1',
            'lignes.*.quantite' => 'required|numeric|min:0',
            'lignes.*.designation' => 'required|string',
            'lignes.*.pu_net' => 'required|numeric|min:0',
            'lignes.*.remise' => 'nullable|numeric|min:0',
            'lignes.*.reference_piece' => 'nullable|string',
            'lignes.*.ne_pas_appliquer_tva' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request) {
            $vehiculeId = $request->vehicule_id;

            if (!$vehiculeId) {
                $client = \App\Models\Client::firstOrCreate(
                    ['nom' => $request->nouveau_client_nom],
                    [
                        'prenom' => $request->nouveau_client_prenom ?? '', 
                        'telephone' => $request->nouveau_client_telephone ?? '00000000'
                    ]
                );

                $vehicule = \App\Models\Vehicule::create([
                    'client_id' => $client->id,
                    'marque' => $request->nouvelle_marque,
                    'modele' => $request->nouveau_modele,
                    'immatriculation' => '', 
                ]);

                $vehiculeId = $vehicule->id;
            }

            $dossier = Intervention::create([
                'vehicule_id' => $vehiculeId,
                'circuit' => 'devis_direct',
                'kilometrage' => 0, 
                'date_reception' => now(),
                'receptionniste_id' => auth()->id(),
                'statut' => 'attente_accord',
                'remarques_eventuelles' => $request->remarques ?? 'Devis direct comptoir.',
            ]);

            $devis = Devis::create([
                'intervention_id' => $dossier->id,
                'createur_id' => auth()->id(),
                'statut' => 'en_attente',
            ]);

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
                    'type' => !empty($ligne['reference_piece']) ? 'piece' : 'main_d_oeuvre',
                ]);
            }
        });

        return redirect()->route('administration.devis.directs.index')
            ->with('success', 'Devis direct enregistré avec succès.');
    }

    public function rechercherPieces(Request $request, Intervention $dossier = null)
    {
        $marque = $request->input('marque');
        $modele = $request->input('modele');

        if ($dossier && $dossier->vehicule) {
            $marque = $marque ?: $dossier->vehicule->marque;
            $modele = $modele ?: $dossier->vehicule->modele;
        }

        $query = $request->input('q', ''); 

        $stocks = Stock::query()
            ->when($marque, function ($q) use ($marque) {
                $q->where('marque', 'LIKE', "%{$marque}%");
            })
            ->when($modele, function ($q) use ($modele) {
                $q->where('modele', 'LIKE', "%{$modele}%");
            })
            ->when($query, function ($q) use ($query) {
                $q->where(function($sub) use ($query) {
                    $sub->where('designation_piece', 'LIKE', "%{$query}%")
                        ->orWhere('reference', 'LIKE', "%{$query}%");
                });
            })
            ->limit(15)
            ->get();

        return response()->json($stocks);
    }
}