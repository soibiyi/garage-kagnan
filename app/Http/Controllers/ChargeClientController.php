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
    public function index()
    {
        $clients = Client::with('vehicules')->latest()->get();

        return Inertia::render('ChargeClient/ClientsIndex', [
            'clients' => $clients
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