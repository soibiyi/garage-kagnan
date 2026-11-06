<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPortalMessage;
use App\Models\ClientPortalRequest;
use App\Models\InteractionClient;
use App\Models\Intervention;
use App\Models\Vehicule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChargeClientController extends Controller
{
    private function estAdmin(): bool
    {
        return auth()->user()->role === 'admin';
    }

    /** Contrainte « dossiers de mon siège » pour les relations chargées. */
    private function dossiersDuSiege(): \Closure
    {
        return fn ($q) => $q->duSiege();
    }

    /** Contrainte « véhicules ayant au moins un dossier dans mon siège » (aucun filtre pour l'admin). */
    private function vehiculesDuSiege(): \Closure
    {
        return fn ($q) => $q->when(
            !$this->estAdmin(),
            fn ($v) => $v->whereHas('interventions', fn ($i) => $i->duSiege())
        );
    }

    /** Refuse (404) l'accès à un véhicule qui n'a aucun dossier dans mon siège. */
    private function autoriserVehicule(Vehicule $vehicule): void
    {
        if ($this->estAdmin()) {
            return;
        }

        abort_unless($vehicule->interventions()->duSiege()->exists(), 404);
    }

    private function autoriserClientPortail(Client $client): void
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'charge_client'], true), 403);

        if ($this->estAdmin()) {
            return;
        }

        $hasDossierAtSiege = $client->vehicules()->whereHas(
            'interventions',
            fn ($query) => $query->where('siege', auth()->user()->siege)
        )->exists();
        $belongsToSiege = !empty(auth()->user()->siege) && $client->siege === auth()->user()->siege;

        abort_unless($hasDossierAtSiege || $belongsToSiege, 404);
    }

    // 1. Liste de tous les clients avec leurs véhicules
    public function index(Request $request)
    {
        $search = $request->input('search');

        $clients = Client::with([
                'vehicules' => $this->vehiculesDuSiege(),
                'vehicules.interventions' => $this->dossiersDuSiege(),
            ])
            ->when(!$this->estAdmin(), fn ($q) => $q->where(function ($clients) {
                $clients->whereHas('vehicules.interventions', fn ($i) => $i->duSiege())
                    ->orWhere(function ($sameSiege) {
                        $siege = auth()->user()->siege;

                        if ($siege) {
                            $sameSiege->where('siege', $siege);
                        } else {
                            $sameSiege->whereRaw('1 = 0');
                        }
                    });
            }))
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
            return $this->estAdmin()
                || (!empty(auth()->user()->siege) && $client->siege === auth()->user()->siege)
                || $client->vehicules->contains(function ($vehicule) {
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
            'vehicules' => $this->vehiculesDuSiege(),
            'vehicules.interventions' => $this->dossiersDuSiege(),
            'vehicules.interventions.devis.lignes',
            'vehicules.interventions.facture.paiements',
            'portalRequests' => fn ($query) => $query->when(
                !$this->estAdmin(),
                fn ($requests) => $requests
                    ->whereNotNull('siege')
                    ->where('siege', auth()->user()->siege)
            )->with(['traitePar:id,name', 'chauffeur:id,name', 'position']),
            'portalRequests.vehicule',
        ]);

        $this->autoriserClientPortail($client);

        return Inertia::render('ChargeClient/ClientShow', [
            'client' => $client,
            'portalAccess' => [
                'identifier' => $client->portal_identifier,
                'temporaryPassword' => $client->temporaryPortalPassword(),
                'passwordChangeRequired' => $client->portal_password_change_required,
            ],
        ]);
    }

        // Page « Demandes » : dépannages, convoyages et messages du client
    public function demandes(Client $client)
    {
        $this->autoriserClientPortail($client);

        $client->load([
            'portalRequests' => fn ($query) => $query
                ->where('type', '!=', 'rendez_vous')
                ->when(
                    !$this->estAdmin(),
                    fn ($requests) => $requests
                        ->whereNotNull('siege')
                        ->where('siege', auth()->user()->siege)
                )
                ->with([
                    'vehicule:id,marque,modele,immatriculation',
                    'chauffeur:id,name',
                    'position',
                ])
                ->latest(),
        ]);

        return Inertia::render('ChargeClient/Clients/ClientDemandes', [
            'client' => $client,
        ]);
    }

    public function acceptPortalAppointment(Client $client, ClientPortalRequest $portalRequest)
    {
        $this->autoriserClientPortail($client);
        abort_unless(
            $portalRequest->client_id === $client->id
                && $portalRequest->type === 'rendez_vous',
            404
        );

        abort_unless(
            $this->estAdmin() || $portalRequest->siege === auth()->user()->siege,
            404
        );

        $updated = ClientPortalRequest::query()
            ->whereKey($portalRequest->id)
            ->where('statut', 'en_attente')
            ->update([
                'statut' => 'accepte',
                'traite_par' => auth()->id(),
                'traite_le' => now(),
                'updated_at' => now(),
            ]);

        abort_unless($updated, 409, 'Cette demande a déjà été traitée.');

        return back()->with('success', 'Le rendez-vous a été accepté et le client en sera informé.');
    }

    public function portalMessages(Request $request, Client $client)
    {
        $this->autoriserClientPortail($client);

        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = ClientPortalMessage::query()
            ->where('client_id', $client->id)
            ->with('user:id,name');
        if (array_key_exists('after_id', $validated)) {
            $query->where('id', '>', $validated['after_id']);
        }
        $messages = $query->orderByDesc('id')->limit(100)->get()->reverse()->values();

        ClientPortalMessage::query()
            ->whereIn('id', $messages->where('sender_type', 'client')->pluck('id'))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map(fn (ClientPortalMessage $message) => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_type === 'client'
                    ? trim($client->prenom.' '.$client->nom)
                    : ($message->user?->name ?? 'Garage Kagnan'),
                'message' => $message->message,
                'created_at' => $message->created_at,
            ]),
        ]);
    }

    public function portalRequestPosition(Client $client, ClientPortalRequest $portalRequest)
    {
        $this->autoriserClientPortail($client);
        abort_unless($portalRequest->client_id === $client->id, 404);
        abort_unless(
            in_array($portalRequest->statut, ['accepte', 'en_cours'], true),
            409,
            'Le suivi est disponible uniquement pendant une mission active.'
        );

        $position = $portalRequest->position;

        return response()->json([
            'position' => $position ? [
                'lat' => (float) $position->latitude,
                'lng' => (float) $position->longitude,
                'updated_at' => $position->updated_at,
            ] : null,
            'client_position' => $portalRequest->client_lat !== null ? [
                'lat' => (float) $portalRequest->client_lat,
                'lng' => (float) $portalRequest->client_lng,
            ] : null,
            'destination_position' => $portalRequest->destination_lat !== null ? [
                'lat' => (float) $portalRequest->destination_lat,
                'lng' => (float) $portalRequest->destination_lng,
            ] : null,
            'statut' => $portalRequest->statut,
        ]);
    }

    public function sendPortalMessage(Request $request, Client $client)
    {
        $this->autoriserClientPortail($client);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = ClientPortalMessage::create([
            'client_id' => $client->id,
            'user_id' => auth()->id(),
            'sender_type' => 'staff',
            'message' => trim($validated['message']),
        ]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => auth()->user()->name,
                'message' => $message->message,
                'created_at' => $message->created_at,
            ],
        ], 201);
    }

    public function resetPortalPassword(Client $client)
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'charge_client'], true), 403);

        if (!$this->estAdmin()) {
            $hasDossierAtSiege = $client->vehicules()->whereHas(
                'interventions',
                fn ($query) => $query->where('siege', auth()->user()->siege)
            )->exists();
            $belongsToSiege = !empty(auth()->user()->siege) && $client->siege === auth()->user()->siege;

            abort_unless($hasDossierAtSiege || $belongsToSiege, 404);
        }

        $client->replaceTemporaryPortalPassword();

        return back()->with('success', 'Un nouveau mot de passe provisoire a été généré.');
    }

    // 3. Fiche d'un véhicule (accueil : 3 cartes + échéances)
    public function showVehicule(Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

        $vehicule->load([
            'client',
            'interventions' => $this->dossiersDuSiege(),
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

    public function updateVehiculeEcheances(Request $request, Vehicule $vehicule)
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'charge_client'], true), 403);
        $this->autoriserVehicule($vehicule);

        $validated = $request->validate([
            'expiration_assurance' => ['nullable', 'date_format:Y-m-d'],
            'expiration_sicta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $vehicule->update($validated);

        return back()->with('success', 'Les dates d’assurance et de visite technique ont été mises à jour.');
    }

    // 3 bis. Page dédiée : infos du véhicule
    public function infosVehicule(Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

        $vehicule->load([
            'client',
            'interventions' => $this->dossiersDuSiege(),
        ]);

        return Inertia::render('ChargeClient/VehiculeInfos', [
            'vehicule' => $vehicule
        ]);
    }

    // 3 ter. Page dédiée : suivi des relances
    public function relancesVehicule(Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

        // Chargement de client.consignes.user pour afficher les consignes admin sur la vue des relances
        $vehicule->load([
            'client.consignes.user',
            'interactions.user:id,name',
        ]);

        return Inertia::render('ChargeClient/VehiculeRelances', [
            'vehicule' => $vehicule
        ]);
    }

    // 3 quater. Page dédiée : interventions, devis et règlements
    public function interventionsVehicule(Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

        $vehicule->load([
            'client',
            'interventions' => $this->dossiersDuSiege(),
            'interventions.devis.lignes',
            'interventions.facture.paiements',
        ]);

        return Inertia::render('ChargeClient/VehiculeInterventions', [
            'vehicule' => $vehicule
        ]);
    }

    // 4. Mettre à jour le statut d'une intervention
    public function updateStatut(Request $request, Intervention $intervention)
    {
        abort_unless($intervention->estVisiblePar(auth()->user()), 404);

        $data = $request->validate([
            'statut' => 'required|string',
        ]);

        $intervention->update([
            'statut' => $data['statut'],
        ]);

        return back()->with('success', 'Statut du véhicule mis à jour avec succès.');
    }

    // 5. Enregistre une interaction au sujet d'un véhicule
    public function storeInteraction(Request $request, Vehicule $vehicule)
    {
        $this->autoriserVehicule($vehicule);

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