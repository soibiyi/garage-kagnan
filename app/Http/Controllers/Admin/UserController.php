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
use Carbon\Carbon;

class UserController extends Controller
{
    // Affiche la liste des utilisateurs via Inertia
   public function index()
{
    $users = User::latest()->get();

    // Totaux des paiements regroupés par mois (12 derniers mois)
    $totauxParMois = Paiement::where('date_paiement', '>=', now()->startOfMonth()->subMonths(11))
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
        'nombre_voitures' => Vehicule::count(),
        'nombre_clients' => Client::count(),
    ];

    return Inertia::render('Admin/Users/Index', [
        'users' => $users,
        'stats' => $stats,
    ]);
}

    // Affiche le formulaire de création
    public function create()
    {
        return Inertia::render('Admin/Users/Create');
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
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé avec succès !');
    }

    // Affiche le formulaire de modification d'un collaborateur
    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
        ]);
    }

    // Met à jour les informations du collaborateur
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|string|in:admin,receptionniste,mecanicien,administratif,charge_client',
            'password' => 'nullable|string|min:6',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => $request->filled('password') ? Hash::make($request->password) : $user->password,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Collaborateur mis à jour avec succès.');
    }

    // Supprimer un utilisateur (avec protection pour ne pas supprimer son propre compte admin)
    public function destroy(User $user)
    {
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
        // Récupère toutes les interactions avec leurs relations
        $interactions = InteractionClient::with(['user', 'client', 'vehicule'])
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
        ]);
    }

    /**
     * Afficher tous les véhicules avec leur propriétaire et leur dernier statut
     */
    public function vehiculesStatus()
    {
        $vehicules = Vehicule::with(['client', 'interventions' => function ($q) {
            $q->latest();
        }])->latest()->get();

        return Inertia::render('Admin/Status', [
            'vehicules' => $vehicules,
        ]);
    }

    /**
 * Historique du chiffre d'affaires par année (avec détail mensuel)
 */
public function chiffreAffaires()
{
    $paiements = Paiement::get(['montant', 'date_paiement']);

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
    ]);
}
}