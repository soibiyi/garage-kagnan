<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Vehicule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChargeClientController extends Controller

{
    // 1. Liste de tous les clients avec leurs véhicules
   // 1. Liste de tous les clients avec leurs véhicules
    public function index(Request $request)
{
    $search = $request->input('search');

    $clients = Client::with(['vehicules.interventions'])
        ->when($search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                // Recherche sur le nom ou prénom du client
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  // Ou recherche par numéro d'OT sur les interventions liées
                  ->orWhereHas('vehicules.interventions', function ($subQuery) use ($search) {
                      $subQuery->where('numero_ot', 'like', "%{$search}%");
                  });
            });
        })
        ->latest()
        ->get();

    // Filtre pour ne garder que ceux qui ont au moins un véhicule en circuit 'normal'
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
    // 2. Détail d'un client spécifique et de ses véhicules
    public function showClient(Client $client)
    {
        $client->load(['vehicules.interventions.devis.lignes']);

        return Inertia::render('ChargeClient/ClientShow', [
            'client' => $client
        ]);
    }

    // 3. Détail d'un véhicule (Historique, Assurances, SICTA, Lignes de devis refusées)
    public function showVehicule(Vehicule $vehicule)
    {
        // On charge toutes les relations nécessaires pour l'historique et les devis
        $vehicule->load([
            'client',
            'interventions.devis.lignes',
            'interventions.mecanicien',
            'interventions.receptionniste'
        ]);

        return Inertia::render('ChargeClient/VehiculeShow', [
            'vehicule' => $vehicule
        ]);
    }
}