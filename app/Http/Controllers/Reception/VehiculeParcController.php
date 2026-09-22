<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VehiculeParcController extends Controller
{
    /**
     * Affiche la liste des véhicules sur le parc et ceux en atelier/administration.
     */
    public function index()
    {
        // 1. Véhicules toujours sur le parc (en attente de prise en charge / au statut réception)
        $interventionsParc = Intervention::with(['vehicule.client', 'receptionniste', 'mecanicien'])
            ->where('statut', 'reception')
            ->latest('date_reception')
            ->get();

        // 2. Véhicules passés en mode atelier / administration (sortis du parc principal)
        $interventionsAtelier = Intervention::with(['vehicule.client', 'receptionniste', 'mecanicien'])
            ->whereIn('statut', ['atelier', 'en_cours', 'attente_accord'])
            ->latest('date_reception')
            ->get();

        // Récupère uniquement les utilisateurs qui ont le rôle 'mecanicien'
        $mecaniciens = User::where('role', 'mecanicien')->get();

        return Inertia::render('Reception/Parc/Index', [
            'interventions' => $interventionsParc,
            'interventionsAtelier' => $interventionsAtelier,
            'mecaniciens' => $mecaniciens,
        ]);
    }

    /**
     * Affiche les détails d'une intervention / véhicule sur le parc.
     */
    public function show($id)
    {
        $intervention = Intervention::with(['vehicule.client', 'receptionniste', 'mecanicien'])
            ->findOrFail($id);

        return Inertia::render('Reception/Parc/Show', [
            'intervention' => $intervention
        ]);
    }

    /**
     * Assigne un mécanicien, enregistre le rapport des pannes et fait avancer le dossier vers l'administration.
     */
    public function updateProgress(Request $request, $id)
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

        // Redirige vers le tableau de bord général pour y voir s'afficher le dossier
        return redirect()->route('dashboard')->with('success', 'Dossier transmis avec succès à l\'administration !');
    }
}