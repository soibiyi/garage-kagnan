<?php

namespace App\Http\Controllers\Mecanicien;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MecanicienController extends Controller
{
    /**
     * Affiche uniquement les interventions dont le statut est 'reception' (Sur le Parc)
     * ainsi que la liste des mécaniciens pour les modales.
     */
    public function index(Request $request)
    {
        // Récupère uniquement les véhicules au statut 'reception'
        $interventions = Intervention::with(['vehicule.client', 'mecanicien'])
            ->where('statut', 'reception')
            ->latest('date_reception')
            ->get();

        // Récupère tous les utilisateurs ayant le rôle 'mecanicien' pour alimenter le select
        $mecaniciens = User::where('role', 'mecanicien')->get();

        return Inertia::render('Mecanicien/Index', [
            'interventions' => $interventions,
            'mecaniciens' => $mecaniciens,
        ]);
    }

    /**
     * Assigne un mécanicien, enregistre le rapport et transmet le dossier à l'administration/atelier
     * (Comportement identique à VehiculeParcController).
     */
    public function progress(Request $request, $id)
    {
        $request->validate([
            'mecanicien_id' => 'required|exists:users,id',
            'rapport_mecanicien' => 'required|string',
        ]);

        $intervention = Intervention::findOrFail($id);

        $intervention->update([
            'mecanicien_id' => $request->mecanicien_id,
            'rapport_mecanicien' => $request->rapport_mecanicien,
            'statut' => 'atelier', // Passe le statut à l'atelier / administration
        ]);

        // Redirige vers le tableau de bord général avec le message de succès
        return redirect()->route('dashboard')->with('success', 'Dossier transmis avec succès à l\'administration !');
    }
}