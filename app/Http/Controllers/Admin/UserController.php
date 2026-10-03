<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicule;
use App\Models\Client;
use App\Models\InteractionClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\Paiement;
use App\Models\Facture;
use App\Models\Intervention;
use Carbon\Carbon;

class UserController extends Controller
{
    private const SESSION_SIEGE = 'admin_siege';

    /**
     * Siège actif de l'administrateur : celui choisi dans le menu déroulant, conservé en session
     * jusqu'à ce qu'il en choisisse un autre. Par défaut : le premier siège de config/sieges.php.
     */
    private function siegeActif(): string
    {
        $codes = array_keys(config('sieges'));
        $siege = session(self::SESSION_SIEGE);

        return in_array($siege, $codes, true) ? $siege : $codes[0];
    }

    /** Enregistre le siège choisi dans le menu déroulant puis revient sur la page courante. */
    public function changerSiege(Request $request)
    {
        $request->validate([
            'siege' => ['required', Rule::in(array_keys(config('sieges')))],
        ]);

        session([self::SESSION_SIEGE => $request->siege]);

        return back();
    }

    /** Un collaborateur n'est accessible que depuis le siège auquel il est rattaché (l'admin est commun). */
    private function verifierAccesCollaborateur(User $user): void
    {
        if ($user->role !== 'admin' && $user->siege !== $this->siegeActif()) {
            abort(404);
        }
    }

    /** Paiements, éventuellement limités aux factures des dossiers d'un siège. */
    private function paiementsQuery(?string $siege)
    {
        $query = Paiement::query();

        if ($siege) {
            $query->whereIn(
                'facture_id',
                Facture::whereIn('intervention_id', Intervention::where('siege', $siege)->select('id'))->select('id')
            );
        }

        return $query;
    }

    // Affiche la liste des utilisateurs via Inertia
   public function index()
{
    $siege = $this->siegeActif();

    // Collaborateurs du siège actif (l'administrateur, commun à tous les sièges, reste visible)
    $users = User::where(fn ($q) => $q->where('siege', $siege)->orWhere('role', 'admin'))
        ->latest()
        ->get();

    // Totaux des paiements regroupés par mois (12 derniers mois)
    $totauxParMois = $this->paiementsQuery($siege)->where('date_paiement', '>=', now()->startOfMonth()->subMonths(11))
        ->get(['montant', 'date_paiement'])
        ->groupBy(fn ($p) => Carbon::parse($p->date_paiement)->format('Y-m'))
        ->map(fn ($groupe) => (float) $groupe->sum('montant'));

    // Liste des 12 derniers mois (du plus récent au plus ancien), mois sans paiement = 0
    $chiffreAffairesMensuel = collect(range(0, 11))->map(function ($i) use ($totauxParMois) {
        $date = now()->startOfMonth()->subMonths($i);
        $cle = $date->format('Y-m');

        return [
            'cle' => $cle,
            'label' => ucfirst($date->locale('fr')->translatedFormat('F Y')),
            'total' => $totauxParMois[$cle] ?? 0,
        ];
    })->values();

    $stats = [
        'chiffre_affaires_mensuel' => $chiffreAffairesMensuel,
        'nombre_voitures' => Vehicule::when($siege, fn ($q) => $q->whereHas('interventions', fn ($i) => $i->where('siege', $siege)))->count(),
        'nombre_clients' => Client::when($siege, fn ($q) => $q->whereHas('vehicules.interventions', fn ($i) => $i->where('siege', $siege)))->count(),
    ];

    return Inertia::render('Admin/Users/Index', [
        'users' => $users,
        'stats' => $stats,
        'siegeFiltre' => $siege,
    ]);
}

    // Affiche le formulaire de création
    public function create()
    {
        return Inertia::render('Admin/Users/Create', [
            'siegeActif' => $this->siegeActif(),
        ]);
    }

    // Enregistre le nouvel utilisateur
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:191|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:admin,receptionniste,mecanicien,administratif,charge_client',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            // Le collaborateur est rattaché au siège actif ; l'administrateur n'a pas de siège (accès à tous)
            'siege' => $request->role === 'admin' ? null : $this->siegeActif(),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé avec succès !');
    }

    // Affiche le formulaire de modification d'un collaborateur
    public function edit(User $user)
    {
        $this->verifierAccesCollaborateur($user);

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
        ]);
    }

    // Met à jour les informations du collaborateur
    public function update(Request $request, User $user)
    {
        $this->verifierAccesCollaborateur($user);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|string|in:admin,receptionniste,mecanicien,administratif,charge_client',
            'password' => 'nullable|string|min:6',
            'siege' => [Rule::requiredIf($request->role !== 'admin'), 'nullable', Rule::in(array_keys(config('sieges')))],
        ], [
            'siege.required' => 'Veuillez attribuer un siège à ce collaborateur.',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'siege' => $request->role === 'admin' ? null : $request->siege,
            'password' => $request->filled('password') ? Hash::make($request->password) : $user->password,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Collaborateur mis à jour avec succès.');
    }

    // Supprimer un utilisateur (avec protection pour ne pas supprimer son propre compte admin)
    public function destroy(User $user)
    {
        $this->verifierAccesCollaborateur($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte administrateur.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Collaborateur supprimé avec succès.');
    }

    /**
     * Affiche le suivi des interactions regroupées par Chargé Client et par Client
     */
    public function interactionIndex()
    {
        $siege = $this->siegeActif();

        // Interactions des chargés client du siège actif uniquement
        $interactions = InteractionClient::with(['user', 'client', 'vehicule'])
            ->whereHas('user', fn ($q) => $q->where('siege', $siege))
            ->latest()
            ->get();

        // Regroupement d'abord par Chargé Client (user_id), puis par Client (client_id)
        $chargesClients = $interactions->groupBy('user_id')->map(function ($userInteractions) {
            $user = $userInteractions->first()->user;

            $clientsGroups = $userInteractions->groupBy(function ($interaction) {
                return $interaction->client_id ?? 'sans_client';
            })->map(function ($group) {
                $premierClient = $group->first()->client;
                return [
                    'client' => $premierClient ? [
                        'id' => $premierClient->id,
                        'nom' => $premierClient->nom_complet ?? $premierClient->nom ?? 'Client non spécifié',
                        'telephone' => $premierClient->telephone ?? null,
                        'email' => $premierClient->email ?? null,
                    ] : [
                        'id' => null,
                        'nom' => 'Client non spécifié',
                    ],
                    'interactions' => $group->values(),
                ];
            })->values();

            return [
                'id' => $user->id ?? null,
                'name' => $user->name ?? 'Chargé client non spécifié',
                'email' => $user->email ?? '',
                'clients_groups' => $clientsGroups,
                'total_interactions' => $userInteractions->count(),
            ];
        })->values();

        return Inertia::render('Admin/Users/InteractionIndex', [
            'chargesClients' => $chargesClients,
            'siegeFiltre' => $siege,
        ]);
    }

    /**
     * Afficher tous les véhicules avec leur propriétaire et leur dernier statut
     */
    public function vehiculesStatus()
    {
        $siege = $this->siegeActif();

        $vehicules = Vehicule::with(['client', 'interventions' => function ($q) use ($siege) {
            $q->when($siege, fn ($i) => $i->where('siege', $siege))->latest();
        }])
            ->when($siege, fn ($q) => $q->whereHas('interventions', fn ($i) => $i->where('siege', $siege)))
            ->latest()
            ->get();

        return Inertia::render('Admin/Status', [
            'vehicules' => $vehicules,
            'siegeFiltre' => $siege,
        ]);
    }

    /**
 * Historique du chiffre d'affaires par année (avec détail mensuel)
 */
public function chiffreAffaires()
{
    $siege = $this->siegeActif();

    // Paiements du siège actif uniquement (aucune comparaison avec les autres sièges)
    $paiements = $this->paiementsQuery($siege)->get(['montant', 'date_paiement']);

    $annees = $paiements
        ->groupBy(fn ($p) => Carbon::parse($p->date_paiement)->year)
        ->map(function ($groupe, $annee) {
            $parMois = $groupe->groupBy(fn ($p) => Carbon::parse($p->date_paiement)->month);

            return [
                'annee' => (int) $annee,
                'total' => (float) $groupe->sum('montant'),
                'nombre_paiements' => $groupe->count(),
                'mois' => collect(range(1, 12))->map(fn ($m) => [
                    'numero' => $m,
                    'label' => ucfirst(Carbon::create(2000, $m, 1)->locale('fr')->translatedFormat('F')),
                    'total' => (float) ($parMois->get($m)?->sum('montant') ?? 0),
                ])->values(),
            ];
        })
        ->sortByDesc('annee')
        ->values();

    return Inertia::render('Admin/ChiffreAffaires', [
        'annees' => $annees,
        'siegeFiltre' => $siege,
    ]);
}
}