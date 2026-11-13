<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityDescriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Supervision en direct : qui est connecté, où il se trouve, et tout ce qu'il fait.
 * Le super administrateur peut aussi ouvrir l'application « en tant que » un collaborateur.
 */
class SupervisionController extends Controller
{
    public function index(Request $request)
    {
        $selection = $request->integer('user') ?: null;

        return Inertia::render('SuperAdmin/Supervision', [
            'utilisateurs' => $this->utilisateurs(),
            // Le journal arrive avec la page : pas d'attente d'un premier appel
            'logsInitiaux' => $this->logs($selection),
            'selectionInitiale' => $selection,
            'roleInitial' => $request->string('role')->toString() ?: null,
            'sieges' => config('sieges'),
        ]);
    }

    /** Présence et dernière activité de chaque utilisateur (rafraîchi en direct). */
    public function etat()
    {
        return response()->json(['utilisateurs' => $this->utilisateurs()]);
    }

    /** Fil d'activité : tous les utilisateurs, ou un seul (?user=ID). ?after=ID / ?before=ID. */
    public function flux(Request $request)
    {
        return response()->json([
            'logs' => $this->logs($request->integer('user') ?: null, $request->integer('after'), $request->integer('before')),
        ]);
    }

    /**
     * Rafraîchissement en direct en UN seul appel (présence + nouvelles actions).
     * Important avec `php artisan serve`, qui ne traite qu'une requête à la fois.
     */
    public function direct(Request $request)
    {
        return response()->json([
            'utilisateurs' => $this->utilisateurs(),
            'logs' => $this->logs($request->integer('user') ?: null, $request->integer('after')),
        ]);
    }

    private function logs(?int $userId, int $after = 0, int $before = 0)
    {
        return ActivityLog::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($after, fn ($q) => $q->where('id', '>', $after))
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->with(['user:id,name,role,is_superadmin', 'impersonator:id,name'])
            ->orderByDesc('id')
            ->limit($after ? 200 : 60)
            ->get()
            ->map(fn (ActivityLog $log) => ActivityDescriber::presenter($log))
            ->values();
    }

    /** Ouvre l'application avec le compte d'un collaborateur (toutes ses actions restent tracées). */
    public function impersonate(Request $request, User $user)
    {
        abort_if($user->role === 'admin', 403, 'Inutile : vous avez déjà tous les droits administrateur.');
        abort_if($request->session()->has('impersonator_id'), 409, 'Revenez d’abord à votre compte.');

        // L'action est enregistrée au nom du super administrateur (et non du compte ouvert)
        $request->attributes->set('activite_user_id', $request->user()->id);
        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($user);

        return redirect()->route('dashboard')->with('success', "Vous naviguez en tant que {$user->name}.");
    }

    private function utilisateurs()
    {
        $debutJournee = now()->startOfDay();

        $derniers = ActivityLog::query()
            ->whereIn('id', ActivityLog::query()->selectRaw('MAX(id)')->groupBy('user_id'))
            ->get()
            ->keyBy('user_id');

        $actionsDuJour = ActivityLog::query()
            ->where('created_at', '>=', $debutJournee)
            ->where('method', '!=', 'GET')
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return User::query()
            ->orderByDesc('last_seen_at')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_superadmin', 'siege', 'last_seen_at'])
            ->map(function (User $u) use ($derniers, $actionsDuJour) {
                $dernier = $derniers->get($u->id);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role_affichage,
                    'siege' => $u->siege,
                    'last_seen_at' => $u->last_seen_at,
                    'en_ligne' => $u->estEnLigne(),
                    'actions_du_jour' => (int) ($actionsDuJour[$u->id] ?? 0),
                    'derniere_activite' => $dernier ? [
                        'description' => ActivityDescriber::decrire($dernier),
                        'url' => $dernier->url,
                        'created_at' => $dernier->created_at,
                    ] : null,
                ];
            })
            ->values();
    }
}
