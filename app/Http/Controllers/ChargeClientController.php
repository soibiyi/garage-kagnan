<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\InteractionClient;
use App\Models\Intervention;
use App\Models\Vehicule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChargeClientController extends Controller
{
    private function estAdmin(): bool
    {
        return auth()->user()->role === 'admin';
    }

    /** Contrainte « dossiers de mon siège » pour les relations chargées. */
    private function dossiersDuSiege(): \Closure
    {
        return fn ($q) => $q->duSiege();
    }

    /** Contrainte « véhicules ayant au moins un dossier dans mon siège » (aucun filtre pour l'admin). */
    private function vehiculesDuSiege(): \Closure
    {
        return fn ($q) => $q->when(
            !$this->estAdmin(),
            fn ($v) => $v->whereHas('interventions', fn ($i) => $i->duSiege())
        );
    }

    /** Refuse (404) l'accès à un véhicule qui n'a aucun dossier dans mon siège. */
    private function autoriserVehicule(Vehicule $vehicule): void
    {
        if ($this->estAdmin()) {
            return;
        }

        abort_unless($vehicule->interventions()->duSiege()->exists(), 404);
    }

    // 1. Liste de tous les clients avec leurs véhicules
    public function index(Request $request)
    {
        $search = $request->input('search');

        $clients = Client::with([
                'vehicules' => $this->vehiculesDuSiege(),
                'vehicules.interventions' => $this->dossiersDuSiege(),
            ])
            // Uniquement les clients ayant un dossier dans mon siège (l'admin voit tout)
            ->when(!$this->estAdmin(), fn ($q) => $q->whereHas('vehicules.interventions', fn ($i) => $i->duSiege()))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('prenom', 'like', "%{$search}%")
                      ->orWhereHas('vehicules.interventions', function ($subQuery) use ($search) {
                          $subQuery->where('numero_ot', 'like', "%{$search}%");
                      });
                });
            })
            ->latest()
            ->get();

        $clientsFiltres = $clients->filter(function ($client) {
            return $client->vehicules->contains(function ($vehicule) {
                return $vehicule->interventions->contains(function ($intervention) {
                    return strtolower($intervention->circuit) === 'normal';
                });
            });
        })->values();

        return Inertia::render('ChargeClient/ClientsIndex', [
            'clients' => $clientsFiltres,
            'filters' => $request->only(['search']),
        ]);
    }

    // 2. Détail d'un client spécifique
    public function showClient(Client $client)
    {
        $client->load([
            'vehicules' => $this->vehiculesDuSiege(),
            'vehicules.interventions' => $this->dossiersDuSiege(),
            'vehicules.interventions.devis.lignes',
            'vehicules.interventions.facture.paiements',
        ]);

        abort_if(!$this->estAdmin() && $client->vehicules->isEmpty(), 404);

        return Inertia::render('ChargeClient/ClientShow', [
            'client' => $client
        ]);
    }

    // 3. Fiche d'un véhicule (accueil : 3 cartes + échéances)
    public function showVehicule(Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

        $vehicule->load([
            'client',
            'interventions' => $this->dossiersDuSiege(),
            'interventions.devis.lignes',
            'interventions.facture.paiements',
            'interventions.mecanicien',
            'interventions.receptionniste',
            'interactions.user:id,name',
        ]);

        return Inertia::render('ChargeClient/VehiculeShow', [
            'vehicule' => $vehicule
        ]);
    }

    // 3 bis. Page dédiée : infos du véhicule
    public function infosVehicule(Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

        $vehicule->load([
            'client',
            'interventions' => $this->dossiersDuSiege(),
        ]);

        return Inertia::render('ChargeClient/VehiculeInfos', [
            'vehicule' => $vehicule
        ]);
    }

    // 3 ter. Page dédiée : suivi des relances
    public function relancesVehicule(Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

        $vehicule->load([
            'client',
            'interactions.user:id,name',
        ]);

        return Inertia::render('ChargeClient/VehiculeRelances', [
            'vehicule' => $vehicule
        ]);
    }

    // 3 quater. Page dédiée : interventions, devis et règlements
    public function interventionsVehicule(Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

        $vehicule->load([
            'client',
            'interventions' => $this->dossiersDuSiege(),
            'interventions.devis.lignes',
            'interventions.facture.paiements',
        ]);

        return Inertia::render('ChargeClient/VehiculeInterventions', [
            'vehicule' => $vehicule
        ]);
    }

    // 4. Mettre à jour le statut d'une intervention (Bouton Véhicule Livré)
    public function updateStatut(Request $request, Intervention $intervention)
    {
        abort_unless($intervention->estVisiblePar(auth()->user()), 404);

        $data = $request->validate([
            'statut' => 'required|string',
        ]);

        $intervention->update([
            'statut' => $data['statut'],
        ]);

        return back()->with('success', 'Statut du véhicule mis à jour avec succès.');
    }

    // 5. Enregistre une interaction (appel, WhatsApp, visite...) au sujet d'un véhicule
    public function storeInteraction(Request $request, Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

        $data = $request->validate([
            'type'                => 'required|in:appel,whatsapp,sms,email,visite,autre',
            'objet'               => 'required|string|max:255',
            'notes'               => 'nullable|string|max:2000',
            'accord_convenu'      => 'nullable|string|max:2000',
            'date_relance_prevue' => 'nullable|date|after_or_equal:today',
        ]);

        InteractionClient::create($data + [
            'client_id'   => $vehicule->client_id,
            'vehicule_id' => $vehicule->id,
            'user_id'     => auth()->id(),
        ]);

        return back()->with('success', 'Interaction enregistrée.');
    }
}