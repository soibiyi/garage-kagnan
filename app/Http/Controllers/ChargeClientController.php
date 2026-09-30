<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\InteractionClient;
use App\Models\Vehicule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChargeClientController extends Controller
{
    // 1. Liste de tous les clients avec leurs véhicules
    public function index(Request $request)
    {
        $search = $request->input('search');

        $clients = Client::with(['vehicules.interventions'])
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
            'vehicules.interventions.devis.lignes',
            'vehicules.interventions.facture.paiements',
        ]);

        return Inertia::render('ChargeClient/ClientShow', [
            'client' => $client
        ]);
    }

    // 3. Fiche d'un véhicule (accueil : 3 cartes + échéances)
    public function showVehicule(Vehicule $vehicule)
    {
        $vehicule->load([
            'client',
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
        $vehicule->load([
            'client',
            'interventions',
        ]);

        return Inertia::render('ChargeClient/VehiculeInfos', [
            'vehicule' => $vehicule
        ]);
    }

    // 3 ter. Page dédiée : suivi des relances
    public function relancesVehicule(Vehicule $vehicule)
    {
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
        $vehicule->load([
            'client',
            'interventions.devis.lignes',
            'interventions.facture.paiements',
        ]);

        return Inertia::render('ChargeClient/VehiculeInterventions', [
            'vehicule' => $vehicule
        ]);
    }

    // 4. Enregistre une interaction (appel, WhatsApp, visite...) au sujet d'un véhicule
    public function storeInteraction(Request $request, Vehicule $vehicule)
    {
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