<?php

namespace App\Http\Controllers\Chauffeur;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalRequest;
use App\Models\ClientPortalRequestPosition;
use App\Models\ClientPortalRequestRejection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ChauffeurController extends Controller
{
    private function chauffeur(): User
    {
        $chauffeur = auth()->user();
        abort_unless($chauffeur && $chauffeur->role === 'chauffeur', 403);
        abort_if(empty($chauffeur->siege), 403, 'Aucun siège n’est associé à ce compte chauffeur.');

        return $chauffeur;
    }

    public function index()
    {
        $chauffeur = $this->chauffeur();

        $activeMission = ClientPortalRequest::query()
            ->where('chauffeur_id', $chauffeur->id)
            ->whereIn('statut', ['accepte', 'en_cours'])
            ->with(['client:id,nom,prenom,telephone', 'vehicule:id,marque,modele,immatriculation', 'position'])
            ->latest('chauffeur_accepted_at')
            ->first();

        $missionsDisponibles = ClientPortalRequest::query()
            ->where('siege', $chauffeur->siege)
            ->whereIn('type', ['depannage', 'convoyage'])
            ->where('statut', 'en_attente')
            ->whereDoesntHave('rejections', fn ($query) => $query->where('chauffeur_id', $chauffeur->id))
            ->with(['client:id,nom,prenom,telephone', 'vehicule:id,marque,modele,immatriculation'])
            ->latest()
            ->get();

        $historiqueMissions = ClientPortalRequest::query()
            ->where('chauffeur_id', $chauffeur->id)
            ->where('siege', $chauffeur->siege)
            ->whereIn('type', ['depannage', 'convoyage'])
            ->where('statut', 'terminee')
            ->with(['client:id,nom,prenom,telephone', 'vehicule:id,marque,modele,immatriculation'])
            ->orderByDesc('mission_completed_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Chauffeur/Index', [
            'activeMission' => $activeMission,
            'missionsDisponibles' => $missionsDisponibles,
            'historiqueMissions' => $historiqueMissions,
            'siege' => $chauffeur->siege,
        ]);
    }

    public function accept(ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();

        DB::transaction(function () use ($portalRequest, $chauffeur) {
            User::query()->lockForUpdate()->findOrFail($chauffeur->id);
            $mission = ClientPortalRequest::query()->lockForUpdate()->findOrFail($portalRequest->id);

            abort_unless(
                $mission->siege === $chauffeur->siege
                    && in_array($mission->type, ['depannage', 'convoyage'], true),
                404
            );
            abort_unless($mission->statut === 'en_attente', 409, 'Cette demande a déjà été prise.');
            abort_if(
                ClientPortalRequest::query()
                    ->where('chauffeur_id', $chauffeur->id)
                    ->whereIn('statut', ['accepte', 'en_cours'])
                    ->lockForUpdate()
                    ->exists(),
                409,
                'Terminez votre mission actuelle avant d’en accepter une autre.'
            );
            abort_if(
                ClientPortalRequestRejection::query()
                    ->where('client_portal_request_id', $mission->id)
                    ->where('chauffeur_id', $chauffeur->id)
                    ->exists(),
                409,
                'Vous avez déjà refusé cette demande.'
            );

            $mission->update([
                'chauffeur_id' => $chauffeur->id,
                'chauffeur_accepted_at' => now(),
                'statut' => 'accepte',
            ]);
        });

        return back()->with('success', 'Mission acceptée. Activez le suivi GPS pour démarrer.');
    }

    public function decline(ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();

        abort_unless(
            $portalRequest->siege === $chauffeur->siege
                && in_array($portalRequest->type, ['depannage', 'convoyage'], true),
            404
        );
        abort_unless($portalRequest->statut === 'en_attente', 409, 'Cette demande n’est plus disponible.');

        ClientPortalRequestRejection::query()->firstOrCreate([
            'client_portal_request_id' => $portalRequest->id,
            'chauffeur_id' => $chauffeur->id,
        ]);

        return back()->with('success', 'Demande refusée. Elle reste proposée aux autres chauffeurs du siège.');
    }

    public function start(ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();
        $mission = $this->missionAttribuee($portalRequest, $chauffeur);
        abort_unless($mission->statut === 'accepte', 409, 'La mission ne peut pas démarrer dans son état actuel.');

        $mission->update([
            'statut' => 'en_cours',
            'mission_started_at' => now(),
        ]);

        return back()->with('success', 'Mission démarrée. Votre position est partagée avec les personnes autorisées.');
    }

    public function complete(ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();

        DB::transaction(function () use ($portalRequest, $chauffeur) {
            $mission = $this->missionAttribuee($portalRequest, $chauffeur, true);
            abort_unless($mission->statut === 'en_cours', 409, 'Seule une mission en cours peut être terminée.');

            $mission->position()->delete();
            $mission->update([
                'statut' => 'terminee',
                'mission_completed_at' => now(),
                'client_lat' => null,
                'client_lng' => null,
                'destination_lat' => null,
                'destination_lng' => null,
            ]);
        });

        return back()->with('success', 'Mission terminée. Les positions précises ont été supprimées.');
    }

    public function updatePosition(Request $request, ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();
        $mission = $this->missionAttribuee($portalRequest, $chauffeur);
        abort_unless($mission->statut === 'en_cours', 409, 'Le suivi GPS est disponible pendant une mission en cours.');

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $position = ClientPortalRequestPosition::query()->updateOrCreate(
            ['client_portal_request_id' => $mission->id],
            [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ]
        );

        return response()->json([
            'position' => [
                'lat' => (float) $position->latitude,
                'lng' => (float) $position->longitude,
                'updated_at' => $position->updated_at,
            ],
        ]);
    }

    private function missionAttribuee(
        ClientPortalRequest $portalRequest,
        User $chauffeur,
        bool $lock = false
    ): ClientPortalRequest {
        $query = ClientPortalRequest::query()
            ->whereKey($portalRequest->id)
            ->where('chauffeur_id', $chauffeur->id)
            ->where('siege', $chauffeur->siege)
            ->whereIn('type', ['depannage', 'convoyage']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }
}
