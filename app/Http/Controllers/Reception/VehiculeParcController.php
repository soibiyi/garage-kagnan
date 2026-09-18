<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use Inertia\Inertia;

class VehiculeParcController extends Controller
{
    /**
     * Affiche la liste de tous les véhicules actuellement sur le parc.
     */
    public function index()
    {
        // Récupère les interventions actives (par exemple : 'reception', 'atelier', etc.)
        // Ajuste les statuts selon ton workflow réel
        $interventions = Intervention::with(['vehicule.client', 'receptionniste'])
            ->whereIn('statut', ['reception', 'atelier', 'en_cours', 'attente_accord'])
            ->latest('date_reception')
            ->get();

        return Inertia::render('Reception/Parc/Index', [
            'interventions' => $interventions
        ]);
    }

    /**
     * Affiche les détails d'une intervention / véhicule sur le parc.
     */
    public function show($id)
    {
        $intervention = Intervention::with(['vehicule.client', 'receptionniste'])
            ->findOrFail($id);

        return Inertia::render('Reception/Parc/Show', [
            'intervention' => $intervention
        ]);
    }
}