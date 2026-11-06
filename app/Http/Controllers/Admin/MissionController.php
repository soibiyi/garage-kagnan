<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MissionController extends Controller
{
    private function completedMissionQuery()
    {
        return ClientPortalRequest::query()
            ->whereIn('type', ['depannage', 'convoyage'])
            ->where('statut', 'terminee')
            ->whereNotNull('chauffeur_id');
    }

    public function history(Request $request)
    {
        $validated = $request->validate([
            'chauffeur_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $chauffeur = isset($validated['chauffeur_id'])
            ? User::query()->where('role', 'chauffeur')->findOrFail($validated['chauffeur_id'])
            : null;

        $missions = $this->completedMissionQuery()
            ->when($chauffeur, fn ($query) => $query->where('chauffeur_id', $chauffeur->id))
            ->select([
                'id',
                'client_id',
                'vehicule_id',
                'siege',
                'type',
                'details',
                'statut',
                'created_at',
                'chauffeur_id',
                'chauffeur_accepted_at',
                'mission_started_at',
                'mission_completed_at',
                'prix_estime',
                'tarif_reference',
            ])
            ->with([
                'client:id,nom,prenom,telephone',
                'vehicule:id,marque,modele,immatriculation',
                'chauffeur:id,name,email,siege',
            ])
            ->orderByDesc('mission_completed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Missions/History', [
            'missions' => $missions,
            'chauffeurFiltre' => $chauffeur
                ? ['id' => $chauffeur->id, 'name' => $chauffeur->name, 'siege' => $chauffeur->siege]
                : null,
        ]);
    }

    public function chauffeursHistory()
    {
        $completed = fn ($query) => $query
            ->whereIn('type', ['depannage', 'convoyage'])
            ->where('statut', 'terminee');

        $chauffeurs = User::query()
            ->where('role', 'chauffeur')
            ->select(['id', 'name', 'email', 'siege'])
            ->withCount(['chauffeurMissions as missions_terminees_count' => $completed])
            ->withSum(['chauffeurMissions as montant_estime_missions_terminees' => $completed], 'prix_estime')
            ->withMax(['chauffeurMissions as derniere_mission_terminee_le' => $completed], 'mission_completed_at')
            ->orderByDesc('missions_terminees_count')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Chauffeurs/History', [
            'chauffeurs' => $chauffeurs,
        ]);
    }
}
