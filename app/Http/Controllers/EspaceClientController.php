<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPortalMessage;
use App\Models\ClientPortalRequest;
use App\Models\Devis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EspaceClientController extends Controller
{
    // Page publique de présentation et de connexion.
    public function index()
    {
        return Inertia::render('EspaceClient/Index', [
            'sieges' => config('sieges'),
        ]);
    }

    // Tableau de bord du client
    public function dashboard()
    {
        return Inertia::render('EspaceClient/Dashboard');
    }

    /* ------------------------------------------------------------------ */
    /* Vues du chargé client : fiche client séparée en 3 pages             */
    /* ------------------------------------------------------------------ */

    // Messages : conversation avec le client
public function chat(Client $client)
{
    ClientPortalMessage::query()
        ->where('client_id', $client->id)
        ->where('sender_type', 'client')
        ->whereNull('read_at')
        ->update(['read_at' => now()]);

    return Inertia::render('ChargeClient/Clients/ClientChat', [   // garde ton chemin Inertia
        'client' => $client->only(['id', 'nom', 'prenom', 'telephone']),
    ]);
}
    // Rendez-vous : demandes de rendez-vous à accepter
    public function rendezVous(Client $client)
    {
        $client->load(['portalRequests' => fn ($query) => $query
            ->where('type', 'rendez_vous')
            ->with('vehicule:id,marque,modele,immatriculation')
            ->latest(),
        ]);

        return Inertia::render('ChargeClient/Clients/ClientRendezVous', [
            'client' => $client,
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        Auth::guard('client')->logout();

        $client = Client::where('portal_identifier', strtoupper(trim($credentials['identifier'])))->first();

        if (!$client || !$client->portal_password || !Hash::check($credentials['password'], $client->portal_password)) {
            return response()->json(['message' => 'Identifiant ou mot de passe incorrect.'], 422);
        }

        Auth::guard('client')->login($client);
        $request->session()->regenerate();

        return response()->json([
            'must_change_password' => $client->portal_password_change_required,
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:191'],
            'siege' => ['required', 'string', Rule::in(array_keys(config('sieges')))],
            'email' => ['nullable', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'vehicle_marque' => ['nullable', 'string', 'max:100'],
            'vehicle_modele' => ['nullable', 'required_with:vehicle_marque,vehicle_immatriculation', 'string', 'max:100'],
            'vehicle_immatriculation' => ['nullable', 'required_with:vehicle_marque,vehicle_modele', 'string', 'max:50', 'unique:vehicules,immatriculation'],
        ]);

        $client = DB::transaction(function () use ($validated) {
            $client = new Client;
            $client->forceFill([
                'prenom' => $validated['prenom'],
                'nom' => $validated['nom'],
                'telephone' => $validated['telephone'],
                'siege' => $validated['siege'],
                'email' => $validated['email'] ?? null,
                'portal_password' => Hash::make($validated['password']),
                'portal_temp_password' => null,
                'portal_password_change_required' => false,
                'portal_registered' => true,
            ])->save();

            if (!empty($validated['vehicle_modele'])) {
                $client->vehicules()->create([
                    'marque' => $validated['vehicle_marque'] ?? null,
                    'modele' => $validated['vehicle_modele'],
                    'immatriculation' => $validated['vehicle_immatriculation'],
                ]);
            }

            return $client;
        });

        Auth::guard('client')->login($client);
        $request->session()->regenerate();

        return response()->json(['identifier' => $client->portal_identifier], 201);
    }

    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me()
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        if ($client->portal_password_change_required) {
            return response()->json(['message' => 'Le changement du mot de passe est obligatoire.'], 423);
        }

        $client->load([
            'vehicules.interventions' => fn ($query) => $query
                ->with(['devis.lignes', 'facture.paiements'])
                ->latest('date_reception'),
            'portalRequests.vehicule',
            'portalRequests.chauffeur:id,name',
            'portalRequests.position',
        ]);

        return response()->json([
            'client' => [
                'id' => $client->id,
                'identifier' => $client->portal_identifier,
                'nom' => $client->nom,
                'prenom' => $client->prenom,
                'telephone' => $client->telephone,
                'email' => $client->email,
                'adresse' => $client->adresse,
                'siege' => [
                    'code' => $client->siege,
                    'nom' => config("sieges.{$client->siege}", $client->siege),
                ],
                'demandes' => $client->portalRequests->map(fn (ClientPortalRequest $demande) => [
                    'id' => $demande->id,
                    'type' => $demande->type,
                    'details' => $demande->details,
                    'statut' => $demande->statut,
                    'created_at' => $demande->created_at,
                    'traite_le' => $demande->traite_le,
                    'chauffeur' => $demande->chauffeur ? [
                        'id' => $demande->chauffeur->id,
                        'name' => $demande->chauffeur->name,
                    ] : null,
                    'prix_estime' => $demande->prix_estime,
                    'tarif_reference' => $demande->tarif_reference,
                    'client_position' => $demande->client_lat !== null ? [
                        'lat' => $demande->client_lat,
                        'lng' => $demande->client_lng,
                    ] : null,
                    'destination_position' => $demande->destination_lat !== null ? [
                        'lat' => $demande->destination_lat,
                        'lng' => $demande->destination_lng,
                    ] : null,
                    'vehicule' => $demande->vehicule ? [
                        'marque' => $demande->vehicule->marque,
                        'modele' => $demande->vehicule->modele,
                        'immatriculation' => $demande->vehicule->immatriculation,
                    ] : null,
                ]),
                'vehicules' => $client->vehicules->map(fn ($vehicule) => [
                    'id' => $vehicule->id,
                    'marque' => $vehicule->marque,
                    'modele' => $vehicule->modele,
                    'immatriculation' => $vehicule->immatriculation,
                    'expiration_assurance' => $vehicule->expiration_assurance,
                    'expiration_sicta' => $vehicule->expiration_sicta,
                    'interventions' => $vehicule->interventions->map(fn ($intervention) => [
                        'id' => $intervention->id,
                        'numero_ot' => $intervention->numero_ot,
                        'date_reception' => $intervention->date_reception,
                        'kilometrage' => $intervention->kilometrage,
                        'statut' => $intervention->statut,
                        'rapport' => $intervention->rapport_mecanicien,
                        'devis' => $intervention->devis ? [
                            'id' => $intervention->devis->id,
                            'statut' => $intervention->devis->statut,
                            'montant_total_ttc' => $intervention->devis->montant_total_ttc,
                            'petite_fourniture_active' => (bool) $intervention->devis->petite_fourniture_active,
                            'petite_fourniture_montant' => $intervention->devis->petite_fourniture_montant,
                            'created_at' => $intervention->devis->created_at,
                            'lignes' => $intervention->devis->lignes->map(fn ($ligne) => [
                                'id' => $ligne->id,
                                'designation' => $ligne->designation,
                                'reference_piece' => $ligne->reference_piece,
                                'quantite' => $ligne->quantite,
                                'pu_net' => $ligne->pu_net,
                                'remise' => $ligne->remise,
                                'montant_ht' => $ligne->montant_ht,
                                'montant_ttc' => $ligne->montant_ttc,
                                'ne_pas_appliquer_tva' => (bool) $ligne->ne_pas_appliquer_tva,
                                'famille' => $ligne->famille,
                                'sous_famille' => $ligne->sous_famille,
                                'is_accepted' => (bool) $ligne->is_accepted,
                            ]),
                        ] : null,
                        'facture' => $intervention->facture ? [
                            'numero_facture' => $intervention->facture->numero_facture,
                            'montant_total' => $intervention->facture->montant_total,
                            'montant_paye' => $intervention->facture->montant_paye,
                            'statut' => $intervention->facture->statut,
                            'paiements' => $intervention->facture->paiements->map(fn ($paiement) => [
                                'montant' => $paiement->montant,
                                'mode_paiement' => $paiement->mode_paiement,
                                'date_paiement' => $paiement->date_paiement,
                            ]),
                        ] : null,
                    ]),
                ]),
            ],
        ]);
    }

    public function updateQuoteSelection(Request $request, Devis $devis)
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        if ($client->portal_password_change_required) {
            return response()->json(['message' => 'Le changement du mot de passe est obligatoire.'], 423);
        }

        $validated = $request->validate([
            'lignes_acceptees' => ['present', 'array'],
            'lignes_acceptees.*' => ['integer', 'distinct'],
        ]);

        return DB::transaction(function () use ($client, $devis, $validated) {
            $devis = Devis::query()
                ->with(['intervention', 'lignes'])
                ->lockForUpdate()
                ->findOrFail($devis->id);

            $intervention = $devis->intervention;
            abort_unless(
                $intervention
                    ->vehicule()
                    ->where('client_id', $client->id)
                    ->exists(),
                404
            );

            $hasPayments = $intervention->facture()->whereHas('paiements')->exists();
            $devisEnAttente = $devis->statut === 'en_attente'
                && $intervention->statut === 'attente_accord'
                && !$hasPayments;
            $devisAccepteSansPaiement = $devis->statut === 'accepte'
                && $intervention->statut === 'accepte'
                && !$hasPayments;

            abort_unless(
                $devisEnAttente || $devisAccepteSansPaiement,
                409,
                'Ce devis ne peut plus être modifié.'
            );

            $acceptedIds = array_map('intval', $validated['lignes_acceptees']);
            $quoteLineIds = $devis->lignes->modelKeys();
            abort_if(
                array_diff($acceptedIds, $quoteLineIds),
                422,
                'Une ou plusieurs lignes ne correspondent pas à ce devis.'
            );

            foreach ($devis->lignes as $ligne) {
                $ligne->update([
                    'is_accepted' => in_array($ligne->id, $acceptedIds, true),
                ]);
            }

            $devis->update(['statut' => 'accepte']);
            $intervention->update(['statut' => 'accepte']);

            return response()->json(['message' => 'Votre choix a été enregistré.']);
        });
    }

    public function storePortalRequest(Request $request)
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        if ($client->portal_password_change_required) {
            return response()->json(['message' => 'Le changement du mot de passe est obligatoire.'], 423);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['message', 'rendez_vous', 'depannage', 'convoyage'])],
            'vehicule_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'required_if:type,message', 'string', 'max:2000'],
            'prestation' => ['nullable', 'required_if:type,rendez_vous', 'string', 'max:150'],
            'date_souhaitee' => ['nullable', 'required_if:type,rendez_vous', 'date', 'after_or_equal:today'],
            'lieu' => ['nullable', 'required_if:type,depannage', 'string', 'max:500'],
            'lieu_depart' => ['nullable', 'required_if:type,convoyage', 'string', 'max:500'],
            'lieu_arrivee' => ['nullable', 'required_if:type,convoyage', 'string', 'max:500'],
            'precision' => ['nullable', 'string', 'max:2000'],
            'client_lat' => ['nullable', 'required_if:type,depannage,convoyage', 'numeric', 'between:-90,90'],
            'client_lng' => ['nullable', 'required_if:type,depannage,convoyage', 'numeric', 'between:-180,180'],
            'destination_lat' => ['nullable', 'required_if:type,convoyage', 'numeric', 'between:-90,90'],
            'destination_lng' => ['nullable', 'required_if:type,convoyage', 'numeric', 'between:-180,180'],
            'gps_consent' => ['exclude_unless:type,depannage,convoyage', 'required', 'accepted'],
            'categorie_tarif' => ['nullable', 'required_if:type,depannage', Rule::in(['voiture_particuliere', 'autre'])],
        ]);

        abort_if(
            in_array($validated['type'], ['depannage', 'convoyage'], true) && empty($client->siege),
            422,
            'Aucun siège n’est associé à votre compte. Contactez le garage avant de demander une mission.'
        );

        if (!empty($validated['vehicule_id'])) {
            abort_unless(
                $client->vehicules()->whereKey($validated['vehicule_id'])->exists(),
                404
            );
        }

        $details = match ($validated['type']) {
            'message' => ['message' => $validated['message']],
            'rendez_vous' => [
                'prestation' => $validated['prestation'],
                'date_souhaitee' => $validated['date_souhaitee'],
                'precision' => $validated['precision'] ?? null,
            ],
            'depannage' => [
                'lieu' => $validated['lieu'],
                'precision' => $validated['precision'] ?? null,
                'categorie_tarif' => $validated['categorie_tarif'],
            ],
            'convoyage' => [
                'lieu_depart' => $validated['lieu_depart'],
                'lieu_arrivee' => $validated['lieu_arrivee'],
                'precision' => $validated['precision'] ?? null,
            ],
        };

        $prixEstime = null;
        $tarifReference = null;
        if (
            $validated['type'] === 'depannage'
            && in_array($client->siege, config('depannage.abidjan_sieges', []), true)
            && $validated['categorie_tarif'] === 'voiture_particuliere'
        ) {
            $heureAbidjan = now()->timezone('Africa/Abidjan')->format('H:i');
            $periode = $heureAbidjan >= config('depannage.nuit_debut') || $heureAbidjan < config('depannage.jour_debut')
                ? 'nuit'
                : 'jour';
            $prixEstime = config("depannage.tarifs.depannage.voiture_particuliere.{$periode}");
            $tarifReference = config('depannage.reference');
        }

        $demande = $client->portalRequests()->create([
            'vehicule_id' => $validated['vehicule_id'] ?? null,
            'siege' => $client->siege,
            'type' => $validated['type'],
            'details' => array_filter($details, fn ($value) => $value !== null && $value !== ''),
            'statut' => 'en_attente',
            'client_lat' => $validated['client_lat'] ?? null,
            'client_lng' => $validated['client_lng'] ?? null,
            'destination_lat' => $validated['destination_lat'] ?? null,
            'destination_lng' => $validated['destination_lng'] ?? null,
            'gps_consent_at' => in_array($validated['type'], ['depannage', 'convoyage'], true) ? now() : null,
            'prix_estime' => $prixEstime,
            'tarif_reference' => $tarifReference,
        ]);

        $message = in_array($validated['type'], ['depannage', 'convoyage'], true)
            ? 'Votre demande a été transmise aux chauffeurs de votre siège.'
            : 'Votre demande a été transmise au Garage Kagnan. Elle reste en attente de confirmation.';

        return response()->json([
            'message' => $message,
            'request' => [
                'id' => $demande->id,
                'statut' => $demande->statut,
                'prix_estime' => $demande->prix_estime,
                'tarif_reference' => $demande->tarif_reference,
            ],
        ], 201);
    }

    public function requestPosition(ClientPortalRequest $portalRequest)
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        if ($client->portal_password_change_required) {
            return response()->json(['message' => 'Le changement du mot de passe est obligatoire.'], 423);
        }

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

    public function portalMessages(Request $request)
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        if ($client->portal_password_change_required) {
            return response()->json(['message' => 'Le changement du mot de passe est obligatoire.'], 423);
        }

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
            ->whereIn('id', $messages->where('sender_type', 'staff')->pluck('id'))
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

    public function sendPortalMessage(Request $request)
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        if ($client->portal_password_change_required) {
            return response()->json(['message' => 'Le changement du mot de passe est obligatoire.'], 423);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = ClientPortalMessage::create([
            'client_id' => $client->id,
            'sender_type' => 'client',
            'message' => trim($validated['message']),
        ]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => trim($client->prenom.' '.$client->nom),
                'message' => $message->message,
                'created_at' => $message->created_at,
            ],
        ], 201);
    }

    public function addVehicle(Request $request)
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        if ($client->portal_password_change_required) {
            return response()->json(['message' => 'Le changement du mot de passe est obligatoire.'], 423);
        }

        $validated = $request->validate([
            'marque' => ['nullable', 'string', 'max:100'],
            'modele' => ['required', 'string', 'max:100'],
            'immatriculation' => ['required', 'string', 'max:50', 'unique:vehicules,immatriculation'],
        ]);

        $vehicle = $client->vehicules()->create($validated);

        return response()->json([
            'vehicle' => [
                'id' => $vehicle->id,
                'marque' => $vehicle->marque,
                'modele' => trim(($vehicle->marque ? $vehicle->marque.' ' : '').$vehicle->modele),
                'immat' => $vehicle->immatriculation,
                'expiration_assurance' => null,
                'expiration_sicta' => null,
                'kilometrage' => null,
            ],
        ], 201);
    }

    public function changePassword(Request $request)
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        if (!Hash::check($validated['current_password'], $client->portal_password)) {
            return response()->json(['message' => 'Le mot de passe provisoire est incorrect.'], 422);
        }

        $client->forceFill([
            'portal_password' => Hash::make($validated['password']),
            'portal_temp_password' => null,
            'portal_password_change_required' => false,
        ])->save();

        return response()->noContent();
    }

    public function updateProfile(Request $request)
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        if ($client->portal_password_change_required) {
            return response()->json(['message' => 'Le changement du mot de passe est obligatoire.'], 423);
        }

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'adresse' => ['nullable', 'string', 'max:2000'],
        ]);

        $client->update($validated);

        return response()->json(['message' => 'Profil mis à jour.']);
    }



    // Refuser une demande de rendez-vous
public function refuseRequest(Client $client, ClientPortalRequest $portalRequest)
{
    abort_unless($portalRequest->client_id === $client->id, 404);
    abort_unless(
        $portalRequest->type === 'rendez_vous' && $portalRequest->statut === 'en_attente',
        409,
        'Ce rendez-vous a déjà été traité.'
    );

    $portalRequest->forceFill([
        'statut' => 'refuse',
        'traite_le' => now(),
    ])->save();

    return back();
}

// Messages clients non lus, tous clients confondus (pour la cloche du layout)
public function messagesNonLus()
{
    $groupes = ClientPortalMessage::query()
        ->where('sender_type', 'client')
        ->whereNull('read_at')
        ->select('client_id', DB::raw('COUNT(*) as nb'), DB::raw('MAX(id) as dernier_id'))
        ->groupBy('client_id')
        ->get();

    if ($groupes->isEmpty()) {
        return response()->json(['total' => 0, 'conversations' => []]);
    }

    $clients = Client::whereIn('id', $groupes->pluck('client_id'))
        ->get(['id', 'nom', 'prenom'])
        ->keyBy('id');
    $derniers = ClientPortalMessage::whereIn('id', $groupes->pluck('dernier_id'))
        ->get(['id', 'message', 'created_at'])
        ->keyBy('id');

    $conversations = $groupes
        ->map(fn ($g) => [
            'client_id' => (int) $g->client_id,
            'nom' => $clients[$g->client_id]->nom ?? '',
            'prenom' => $clients[$g->client_id]->prenom ?? '',
            'nb' => (int) $g->nb,
            'dernier_id' => (int) $g->dernier_id,
            'apercu' => \Illuminate\Support\Str::limit($derniers[$g->dernier_id]->message ?? '', 80),
            'created_at' => $derniers[$g->dernier_id]->created_at ?? null,
        ])
        ->sortByDesc('dernier_id')
        ->values();

    return response()->json([
        'total' => $conversations->sum('nb'),
        'conversations' => $conversations,
    ]);
}
}