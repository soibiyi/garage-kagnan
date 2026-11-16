<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Connexion;
use App\Models\User;
use App\Support\Parametres;
use App\Support\Securite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Sécurité des comptes : connexions, sessions ouvertes, déconnexion forcée, suspension. */
class SecuriteController extends Controller
{
    public function index(Request $request)
    {
        $filtre = in_array($request->query('filtre'), ['echecs', 'hors_horaires', 'reussies'], true) ? $request->query('filtre') : null;
        $userId = $request->integer('user') ?: null;

        $sessions = DB::table('sessions')->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) AS n, MAX(last_activity) AS derniere')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $dernieresConnexions = Connexion::query()
            ->whereIn('id', Connexion::query()->where('reussie', true)->selectRaw('MAX(id)')->groupBy('user_id'))
            ->get()->keyBy('user_id');

        $echecs24h = Connexion::query()->where('reussie', false)->where('created_at', '>=', now()->subDay())
            ->whereNotNull('user_id')->selectRaw('user_id, COUNT(*) AS n')->groupBy('user_id')->pluck('n', 'user_id');

        $comptes = User::query()
            ->orderByRaw('suspendu_at IS NULL')
            ->orderByDesc('last_seen_at')
            ->orderBy('name')
            ->with('suspenduPar:id,name')
            ->get()
            ->map(function (User $u) use ($sessions, $dernieresConnexions, $echecs24h) {
                $s = $sessions->get($u->id);
                $c = $dernieresConnexions->get($u->id);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role_affichage,
                    'siege' => $u->siege,
                    'superadmin' => $u->estSuperAdmin(),
                    'en_ligne' => $u->estEnLigne(),
                    'sessions' => (int) ($s->n ?? 0),
                    'derniere_activite' => $s ? date(DATE_ATOM, (int) $s->derniere) : null,
                    'derniere_connexion' => $c ? [
                        'date' => $c->created_at,
                        'ip' => $c->ip,
                        'appareil' => Securite::appareil($c->user_agent),
                        'hors_horaires' => $c->hors_horaires,
                    ] : null,
                    'echecs_24h' => (int) ($echecs24h[$u->id] ?? 0),
                    'suspendu' => $u->suspendu_at ? [
                        'depuis' => $u->suspendu_at,
                        'motif' => $u->suspendu_motif,
                        'par' => $u->suspenduPar?->name,
                    ] : null,
                ];
            });

        $connexions = Connexion::query()
            ->when($filtre === 'echecs', fn ($q) => $q->where('reussie', false))
            ->when($filtre === 'reussies', fn ($q) => $q->where('reussie', true))
            ->when($filtre === 'hors_horaires', fn ($q) => $q->where('hors_horaires', true)->where('reussie', true))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->with('user:id,name,role')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Connexion $c) => [
                'id' => $c->id,
                'date' => $c->created_at,
                'nom' => $c->user?->name,
                'email' => $c->email,
                'reussie' => $c->reussie,
                'motif' => $c->motif,
                'hors_horaires' => $c->hors_horaires,
                'ip' => $c->ip,
                'appareil' => Securite::appareil($c->user_agent),
            ]);

        $depuis = now()->subDay();

        return Inertia::render('SuperAdmin/Securite', [
            'comptes' => $comptes,
            'connexions' => $connexions,
            'filtre' => $filtre,
            'userFiltre' => $userId,
            'horaires' => ['debut' => Parametres::get('heure_ouverture'), 'fin' => Parametres::get('heure_fermeture')],
            'resume' => [
                'connexions_24h' => Connexion::where('reussie', true)->where('created_at', '>=', $depuis)->count(),
                'echecs_24h' => Connexion::where('reussie', false)->where('created_at', '>=', $depuis)->count(),
                'hors_horaires_7j' => Connexion::where('reussie', true)->where('hors_horaires', true)->where('created_at', '>=', now()->subDays(7))->count(),
                'suspendus' => User::whereNotNull('suspendu_at')->count(),
            ],
        ]);
    }

    public function deconnecter(Request $request, User $user)
    {
        $this->verifierCible($request, $user);
        $n = Securite::deconnecterPartout($user);

        return back()->with('success', "{$user->name} a été déconnecté" . ($n ? " ({$n} session(s) fermée(s))." : ' (aucune session ouverte).'));
    }

    public function suspendre(Request $request, User $user)
    {
        $this->verifierCible($request, $user);
        $data = $request->validate(['motif' => ['nullable', 'string', 'max:250']]);

        $user->forceFill([
            'suspendu_at' => now(),
            'suspendu_motif' => $data['motif'] ?? null,
            'suspendu_par' => $request->user()->id,
        ])->save();
        Securite::deconnecterPartout($user);

        return back()->with('success', "Le compte de {$user->name} est suspendu et toutes ses sessions sont fermées.");
    }

    public function reactiver(User $user)
    {
        $user->forceFill(['suspendu_at' => null, 'suspendu_motif' => null, 'suspendu_par' => null])->save();

        return back()->with('success', "Le compte de {$user->name} est réactivé.");
    }

    private function verifierCible(Request $request, User $user): void
    {
        abort_if($user->id === $request->user()->id, 422, 'Impossible sur votre propre compte.');
        abort_if($user->estSuperAdmin(), 403, 'Impossible sur un autre super administrateur.');
    }
}
