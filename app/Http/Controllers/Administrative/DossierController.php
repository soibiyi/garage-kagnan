<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Devis;
use App\Models\LigneDevis;
use App\Models\Facture;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Stock;
use App\Support\PetiteFourniture;

class DossierController extends Controller
{
    /** Refuse (404) l'accès à un dossier d'un autre siège. */
    private function autoriser(Intervention $dossier): void
    {
        abort_unless($dossier->estVisiblePar(auth()->user()), 404);
    }

    /**
     * Redirection après une action sur les devis (création, modification, validation) :
     * le chargé de suivi client retourne sur son propre dashboard,
     * les autres rôles reviennent à la liste d'origine.
     */
    private function redirectApresAction(string $message, string $routeParDefaut = 'administration.facturation.index')
    {
        $route = auth()->user()->role === 'charge_client'
            ? 'dashboard'
            : $routeParDefaut;

        return redirect()->route($route)->with('success', $message);
    }

    public function index()
    {
        $dossiers = Intervention::duSiege()->with(['vehicule.client', 'mecanicien', 'receptionniste'])
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
        $dossiers = Intervention::duSiege()->with(['vehicule.client', 'devis', 'mecanicien'])
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
        $this->autoriser($dossier);

        $dossier->load(['vehicule.client', 'devis.lignes']);

        return Inertia::render('Administration/FacturationShow', [
            'dossier' => $dossier
        ]);
    }

    public function updateDevisValidation(Request $request, Intervention $dossier)
    {
        $this->autoriser($dossier);

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

        // Redirection : dashboard du chargé de suivi client, sinon liste d'attente
        return $this->redirectApresAction('Choix du client enregistré avec succès. Le dossier a basculé dans les devis validés.');
    }

    public function show(Intervention $dossier)
    {
        $this->autoriser($dossier);

        $dossier->load(['vehicule.client', 'mecanicien', 'receptionniste']);

        return Inertia::render('Administration/DossierShow', [
            'dossier' => $dossier
        ]);
    }

    public function storeDevis(Request $request, Intervention $dossier)
    {
        $this->autoriser($dossier);

        $request->validate([
            'lignes' => 'required|array|min:1',
            'lignes.*.quantite' => 'required|numeric|min:0',
            'lignes.*.designation' => 'required|string',
            'lignes.*.pu_net' => 'required|numeric|min:0',
            'lignes.*.remise' => 'nullable|numeric|min:0|max:100', // pourcentage
            'lignes.*.reference_piece' => 'nullable|string',
            'lignes.*.ne_pas_appliquer_tva' => 'nullable|boolean',
            'lignes.*.famille' => 'nullable|string|max:100',
            'lignes.*.sous_famille' => 'nullable|string|max:100',
            'petite_fourniture_active' => 'nullable|boolean',
            'petite_fourniture_montant' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $dossier) {
            // Création de l'entête du devis
            $devis = Devis::create([
                'intervention_id' => $dossier->id,
                'createur_id' => auth()->id(),
                'statut' => 'en_attente',
            ]);

            // Enregistrement des lignes du devis
            $this->creerLignesDevis($devis, $request->lignes);
            $this->appliquerPetiteFourniture($devis, $request);

            // Mise à jour du statut du dossier
            $dossier->update(['statut' => 'attente_accord']);
        });

        return $this->redirectApresAction('Devis enregistré avec succès.', 'administration.dossiers.index');
    }

    /**
     * Seul un devis encore en attente de validation client peut être modifié.
     */
    private function verifierDevisModifiable(Intervention $dossier): void
    {
        abort_unless(
            $dossier->statut === 'attente_accord'
                && $dossier->devis
                && $dossier->devis->statut === 'en_attente',
            403,
            'Ce devis ne peut plus être modifié.'
        );
    }

    // MODIFICATION : affiche le formulaire de devis pré-rempli avec les lignes existantes
    public function editDevis(Intervention $dossier)
    {
        $this->autoriser($dossier);
        $this->verifierDevisModifiable($dossier);

        $dossier->load(['vehicule.client', 'mecanicien', 'receptionniste', 'devis.lignes']);

        return Inertia::render('Administration/DossierShow', [
            'dossier' => $dossier,
            'devis'   => $dossier->devis,
        ]);
    }

    // MODIFICATION : remplace les lignes du devis, le statut du dossier ne change pas
    public function updateDevis(Request $request, Intervention $dossier)
    {
        $this->autoriser($dossier);
        $this->verifierDevisModifiable($dossier);

        $request->validate([
            'lignes' => 'required|array|min:1',
            'lignes.*.quantite' => 'required|numeric|min:0',
            'lignes.*.designation' => 'required|string',
            'lignes.*.pu_net' => 'required|numeric|min:0',
            'lignes.*.remise' => 'nullable|numeric|min:0|max:100', // pourcentage
            'lignes.*.reference_piece' => 'nullable|string',
            'lignes.*.ne_pas_appliquer_tva' => 'nullable|boolean',
            'lignes.*.famille' => 'nullable|string|max:100',
            'lignes.*.sous_famille' => 'nullable|string|max:100',
            'petite_fourniture_active' => 'nullable|boolean',
            'petite_fourniture_montant' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $dossier) {
            $devis = $dossier->devis;

            // Le client n'a encore rien validé : on peut remplacer les lignes
            $devis->lignes()->delete();
            $this->creerLignesDevis($devis, $request->lignes);
            $this->appliquerPetiteFourniture($devis, $request);
            $devis->touch();
        });

        return $this->redirectApresAction('Devis modifié avec succès.');
    }

    // NOUVELLE MÉTHODE : Vue globale de tous les devis pour l'administration
    public function devisIndex()
    {
        $dossiers = Intervention::duSiege()->with(['vehicule.client', 'devis.lignes', 'mecanicien', 'receptionniste'])
            ->has('devis')
            ->latest()
            ->get();

        return Inertia::render('Administration/DevisIndex', [
            'dossiers' => $dossiers
        ]);
    }

    // NOUVELLE VUE : Devis validés par le client (filtrés par circuit normal et recherche)
    public function devisAcceptesIndex(Request $request)
    {
        $search = $request->input('search');

        $dossiers = Intervention::duSiege()->with(['vehicule.client', 'devis.lignes', 'mecanicien'])
            ->where('statut', 'accepte')
            ->where('circuit', 'normal') // On filtre par circuit normal
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    // Recherche par numéro d'OT
                    $q->where('numero_ot', 'like', "%{$search}%")
                      // Ou recherche par nom/prénom du client via la relation vehicule.client
                      ->orWhereHas('vehicule.client', function ($subQuery) use ($search) {
                          $subQuery->where('nom', 'like', "%{$search}%")
                                   ->orWhere('prenom', 'like', "%{$search}%");
                      });
                });
            })
            ->latest()
            ->get();

        return Inertia::render('Administration/DevisAcceptesIndex', [
            'dossiers' => $dossiers,
            'filters' => $request->only(['search']),
        ]);
    }

    // NOUVELLE VUE : Historique global de tous les devis (acceptés et refusés)
    public function devisHistoriqueIndex()
    {
        $dossiers = Intervention::duSiege()->with(['vehicule.client', 'devis.lignes', 'mecanicien'])
            ->has('devis')
            ->latest()
            ->get();

        return Inertia::render('Administration/DevisHistoriqueIndex', [
            'dossiers' => $dossiers
        ]);
    }

    // Affiche la liste des devis directs avec le résumé des paiements pour actualiser le statut "Soldé"
    public function devisDirectIndex()
    {
        $devisDirects = Intervention::duSiege()->with(['vehicule.client', 'devis.lignes', 'receptionniste'])
            ->where('circuit', 'devis_direct')
            ->latest()
            ->get();

        // Récupérer les factures associées pour calculer le statut de paiement
        $factures = Facture::whereIn('intervention_id', $devisDirects->pluck('id'))
            ->get()
            ->keyBy('intervention_id');

        $devisDirects->each(function ($d) use ($factures) {
            $facture = $factures->get($d->id);
            $paye = (int) round((float) optional($facture)->montant_paye);

            $total = $d->devis
                ? PetiteFourniture::totalFinal($d->devis, (float) $d->devis->lignes->sum('montant_ttc'))
                : 0;

            $d->setAttribute('resume_paiement', [
                'total_ttc'    => $total,
                'montant_paye' => $paye,
                'reste'        => max($total - $paye, 0),
                'soldee'       => $total > 0 && $paye >= $total,
            ]);
        });

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
            'lignes.*.remise' => 'nullable|numeric|min:0|max:100', // pourcentage
            'lignes.*.reference_piece' => 'nullable|string',
            'lignes.*.ne_pas_appliquer_tva' => 'nullable|boolean',
            'lignes.*.famille' => 'nullable|string|max:100',
            'lignes.*.sous_famille' => 'nullable|string|max:100',
            'petite_fourniture_active' => 'nullable|boolean',
            'petite_fourniture_montant' => 'nullable|integer|min:0',
        ]);

        $user = auth()->user();
        if ($user->role !== 'admin' && empty($user->siege)) {
            return back()->with('error', "Aucun siège ne vous est attribué. Contactez l'administrateur.");
        }
        $siege = $user->siege ?: array_key_first(config('sieges'));

        DB::transaction(function () use ($request, $siege) {
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
                    'immatriculation' => 'TEMP-' . uniqid(),
                ]);

                $vehiculeId = $vehicule->id;
            }

            $dossier = Intervention::create([
                'vehicule_id' => $vehiculeId,
                'circuit' => 'devis_direct',
                'siege' => $siege,
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

            $this->creerLignesDevis($devis, $request->lignes);

            $this->appliquerPetiteFourniture($devis, $request);
        });

        return $this->redirectApresAction('Devis direct enregistré avec succès.', 'administration.devis.directs.index');
    }

    public function rechercherPieces(Request $request, ?Intervention $dossier = null)
    {
        if ($dossier) {
            $this->autoriser($dossier);
        }

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

    /**
     * Petite fourniture : montant saisi (obligatoirement appliqué) ou 3 % automatique.
     * Une saisie manuelle verrouille la case : elle ne peut plus être décochée.
     */
    private function appliquerPetiteFourniture(Devis $devis, Request $request): void
    {
        $manuel = $request->filled('petite_fourniture_montant');

        $devis->forceFill([
            'petite_fourniture_montant' => $manuel ? (int) $request->input('petite_fourniture_montant') : null,
            'petite_fourniture_active'  => $manuel ? true : $request->boolean('petite_fourniture_active', true),
        ])->save();
    }

    private function creerLignesDevis(Devis $devis, array $lignes): void
    {
        foreach ($lignes as $ligne) {
            $qte = $ligne['quantite'] ?? 1;
            $pu = $ligne['pu_net'] ?? 0;
            $remise = $ligne['remise'] ?? 0;

            // La remise est un POURCENTAGE du montant de la ligne (qté × PU)
            $remise = min(max((float) $remise, 0), 100);
            $ht = ($qte * $pu) * (1 - $remise / 100);
            $nePasAppliquerTva = $ligne['ne_pas_appliquer_tva'] ?? false;
            $ttc = $nePasAppliquerTva ? $ht : $ht * 1.18;

            $famille = !empty($ligne['famille']) ? $ligne['famille'] : null;
            $sousFamille = ($famille && !empty($ligne['sous_famille'])) ? $ligne['sous_famille'] : null;

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
                'famille' => $famille,
                'sous_famille' => $sousFamille,
            ]);
        }
    }
}