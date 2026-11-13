<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ErrorLog;
use App\Models\User;
use Inertia\Inertia;

/**
 * Tableau de bord du super administrateur :
 *  - ses outils propres (supervision, erreurs, petites fournitures, comptes) ;
 *  - « Agir en tant que » : une carte par rôle pour ouvrir l'application avec un compte de ce rôle.
 */
class AccueilController extends Controller
{
    public function index()
    {
        $comptes = User::query()
            ->where('role', '!=', 'admin')
            ->orderByDesc('last_seen_at')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'siege', 'last_seen_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                // « depaneur » (ancienne orthographe) est rangé avec les dépanneurs
                'role' => $u->role === 'depaneur' ? 'depanneur' : $u->role,
                'siege' => $u->siege,
                'en_ligne' => $u->estEnLigne(),
                'last_seen_at' => $u->last_seen_at,
            ])
            ->groupBy('role');

        return Inertia::render('SuperAdmin/Accueil', [
            // Tableau de bord administrateur intégré (bloc 3) : mêmes données que la page admin
            'admin' => app(UserController::class)->donneesTableauDeBord(),
            'comptesParRole' => $comptes,
            'stats' => [
                'en_ligne' => User::where('last_seen_at', '>', now()->subMinutes(2))->count(),
                'erreurs_ouvertes' => ErrorLog::whereNull('resolue_at')->count(),
                'actions_aujourdhui' => ActivityLog::where('created_at', '>=', now()->startOfDay())->where('method', '!=', 'GET')->count(),
                'administrateurs' => User::where('role', 'admin')->count(),
            ],
        ]);
    }
}
