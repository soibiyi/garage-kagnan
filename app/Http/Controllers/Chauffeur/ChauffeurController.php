<?php

namespace App\Http\Controllers\Chauffeur;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalRequest;
use App\Models\ClientPortalRequestPosition;
use App\Models\ClientPortalRequestRejection;
use App\Models\ClientPortalMessage;
use App\Models\User;
use App\Notifications\GarageMissionArrived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ChauffeurController extends Controller
{
    private function chauffeur(): User
    {
        $chauffeur = auth()->user();
        abort_unless($chauffeur && in_array($chauffeur->role, ['chauffeur', 'depanneur', 'depaneur'], true), 403);
        abort_if(empty($chauffeur->siege), 403, 'Aucun siège n’est associé à ce compte.');

        return $chauffeur;
    }

    private function typesAutorises(): array
    {
        $role = auth()->user()?->role;
        return in_array($role, ['depanneur', 'depaneur'], true) ? ['depannage'] : ['convoyage'];
    }

    public function index()
    {
        $chauffeur = $this->chauffeur();

        $activeMission = ClientPortalRequest::query()
            ->where('chauffeur_id', $chauffeur->id)
            ->whereIn('statut', ['accepte', 'en_cours', 'en_route_recuperation', 'vehicule_recupere', 'en_route_garage'])
            ->with(['client:id,nom,prenom,telephone', 'vehicule:id,marque,modele,immatriculation', 'position'])
            ->latest('chauffeur_accepted_at')
            ->first();

        $missionsDisponibles = ClientPortalRequest::query()
            ->where('siege', $chauffeur->siege)
            ->whereIn('type', $this->typesAutorises())
            ->where('statut', 'en_attente')
            ->whereDoesntHave('rejections', fn ($query) => $query->where('chauffeur_id', $chauffeur->id))
            ->with(['client:id,nom,prenom,telephone', 'vehicule:id,marque,modele,immatriculation'])
            ->latest()
            ->get();

        $historiqueMissions = ClientPortalRequest::query()
            ->where('chauffeur_id', $chauffeur->id)
            ->where('siege', $chauffeur->siege)
            ->whereIn('type', $this->typesAutorises())
            ->whereIn('statut', ['terminee', 'vehicule_arrive_au_garage'])
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
                    && in_array($mission->type, $this->typesAutorises(), true),
                404
            );
            abort_unless($mission->statut === 'en_attente', 409, 'Cette demande a déjà été prise.');
            abort_if(
                ClientPortalRequest::query()
                    ->where('chauffeur_id', $chauffeur->id)
                    ->whereIn('statut', ['accepte', 'en_cours', 'en_route_recuperation', 'vehicule_recupere', 'en_route_garage'])
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

        return back()->with('success', 'Demande acceptée. Activez le GPS pour rejoindre le véhicule.');
    }

    public function decline(ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();

        abort_unless(
            $portalRequest->siege === $chauffeur->siege
                && in_array($portalRequest->type, $this->typesAutorises(), true),
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
        DB::transaction(function () use ($portalRequest, $chauffeur) {
            $mission = $this->missionAttribuee($portalRequest, $chauffeur, true);
            if ($mission->statut === 'en_route_recuperation') {
                return;
            }
            abort_unless($mission->statut === 'accepte', 409, 'La mission ne peut pas démarrer dans son état actuel.');

            $mission->update([
                'statut' => 'en_route_recuperation',
                'mission_started_at' => now(),
                'distance_parcourue_km' => 0,
            ]);
        });

        return back()->with('success', 'Navigation vers le véhicule démarrée.');
    }

    public function arrivePickup(ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();
        DB::transaction(function () use ($portalRequest, $chauffeur) {
            $mission = $this->missionAttribuee($portalRequest, $chauffeur, true);
            abort_unless(in_array($mission->statut, ['en_route_recuperation', 'en_cours'], true), 409, 'Vous devez être en route vers le véhicule.');
            $mission->update([
                'statut' => 'vehicule_recupere',
                'recovery_confirmed_at' => now(),
            ]);
        });

        return back()->with('success', 'Arrivée confirmée. Vérifiez le véhicule avant de partir au garage.');
    }

    public function startGarage(Request $request, ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();
        DB::transaction(function () use ($request, $portalRequest, $chauffeur) {
            $mission = $this->missionAttribuee($portalRequest, $chauffeur, true);
            abort_unless($mission->statut === 'vehicule_recupere', 409, 'La récupération du véhicule doit être confirmée avant le trajet garage.');

            $validated = $request->validate([
                'vehicule_securise' => ['required', 'accepted'],
                'etat_vehicule_verifie' => ['required', 'accepted'],
            ]);
            $details = $mission->details ?? [];
            $details['vehicule_securise'] = (bool) $validated['vehicule_securise'];
            $details['etat_vehicule_verifie'] = (bool) $validated['etat_vehicule_verifie'];

            $mission->update([
                'statut' => 'en_route_garage',
                'details' => $details,
            ]);
        });

        return back()->with('success', 'Trajet vers le Garage Kagnan démarré.');
    }

    public function complete(ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();

        $completion = DB::transaction(function () use ($portalRequest, $chauffeur) {
            $mission = $this->missionAttribuee($portalRequest, $chauffeur, true);
            abort_unless($mission->statut === 'en_route_garage', 409, 'Le trajet vers le garage doit être démarré avant la livraison.');
            $mission->position()->delete();
            $mission->update([
                'statut' => 'vehicule_arrive_au_garage',
                'mission_completed_at' => now(),
                'garage_arrival_at' => now(),
                'client_lat' => null,
                'client_lng' => null,
                'destination_lat' => null,
                'destination_lng' => null,
            ]);

            $message = ClientPortalMessage::create([
                'client_id' => $mission->client_id,
                'user_id' => $chauffeur->id,
                'sender_type' => 'staff',
                'message' => 'Votre véhicule est bien arrivé au Garage Kagnan.',
            ]);

            return [
                'message' => $message,
                'summary' => [
                    'type' => $mission->type,
                    'mission_started_at' => $mission->mission_started_at,
                    'mission_completed_at' => $mission->mission_completed_at,
                    'distance_parcourue_km' => $mission->distance_parcourue_km,
                    'prix_estime' => $mission->prix_estime,
                ],
            ];
        });

        $admins = User::query()
            ->where('role', 'admin')
            ->where(fn ($query) => $query->whereNull('siege')->orWhere('siege', $chauffeur->siege))
            ->get();
        Notification::send($admins, new GarageMissionArrived($portalRequest->id, $chauffeur->siege, $completion['message']->created_at));

        return back()
            ->with('success', 'Livraison confirmée. Le client et l’administration ont été notifiés.')
            ->with('missionSummary', $completion['summary']);
    }

    public function updatePosition(Request $request, ClientPortalRequest $portalRequest)
    {
        $chauffeur = $this->chauffeur();
        $mission = $this->missionAttribuee($portalRequest, $chauffeur);
        abort_unless(in_array($mission->statut, ['en_cours', 'en_route_recuperation', 'en_route_garage'], true), 409, 'Le suivi GPS est disponible pendant un trajet actif.');

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $previousPosition = $mission->position;
        $distanceKm = (float) $mission->distance_parcourue_km;
        if ($previousPosition) {
            $segmentMeters = $this->distanceInMeters(
                (float) $previousPosition->latitude,
                (float) $previousPosition->longitude,
                (float) $validated['latitude'],
                (float) $validated['longitude']
            );
            if ($segmentMeters >= 5) {
                $distanceKm += $segmentMeters / 1000;
            }
        }

        $position = ClientPortalRequestPosition::query()->updateOrCreate(
            ['client_portal_request_id' => $mission->id],
            [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ]
        );

        $mission->update(['distance_parcourue_km' => round($distanceKm, 2)]);

        return response()->json([
            'position' => [
                'lat' => (float) $position->latitude,
                'lng' => (float) $position->longitude,
                'updated_at' => $position->updated_at,
            ],
            'distance_parcourue_km' => (float) $mission->fresh()->distance_parcourue_km,
        ]);
    }

    private function distanceInMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $toRadians = fn (float $value) => $value * M_PI / 180;

        $deltaLat = $toRadians($lat2 - $lat1);
        $deltaLng = $toRadians($lng2 - $lng1);
        $latitude1 = $toRadians($lat1);
        $latitude2 = $toRadians($lat2);

        $a = sin($deltaLat / 2) ** 2 + cos($latitude1) * cos($latitude2) * sin($deltaLng / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
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
            ->whereIn('type', $this->typesAutorises());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }
}
