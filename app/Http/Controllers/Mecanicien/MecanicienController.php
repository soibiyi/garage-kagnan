<?php

namespace App\Http\Controllers\Mecanicien;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MecanicienController extends Controller
{
    /**
     * Affiche uniquement les interventions dont le statut est 'reception' (Sur le Parc).
     */
    public function index(Request $request)
    {
        // Récupère uniquement les véhicules au statut 'reception'
        $interventions = Intervention::with(['vehicule.client', 'mecanicien'])
            ->where('statut', 'reception')
            ->latest('date_reception')
            ->get();

        return Inertia::render('Mecanicien/Index', [
            'interventions' => $interventions,
        ]);
    }

    /**
     * Met à jour le rapport ou l'état de l'intervention.
     */
    public function progress(Request $request, Intervention $intervention)
    {
        $validated = $request->validate([
            'rapport_mecanicien' => 'required|string',
            'statut' => 'nullable|string',
        ]);

        $intervention->update([
            'rapport_mecanicien' => $validated['rapport_mecanicien'],
            'statut' => $validated['statut'] ?? $intervention->statut,
        ]);

        return redirect()->back()->with('success', 'Rapport mis à jour avec succès.');
    }
}