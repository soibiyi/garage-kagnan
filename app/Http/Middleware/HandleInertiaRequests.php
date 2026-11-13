<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
  public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role, // <-- C'est cette ligne qui manquait pour Vue.js
                    'siege' => $request->user()->siege, // SGK, ZGK, YGK (null pour l'admin)
                    'is_superadmin' => $request->user() instanceof \App\Models\User && $request->user()->estSuperAdmin(),
                ] : null,
                // Super administrateur en train de naviguer « en tant que » cet utilisateur
                'impersonation' => fn () => ($id = $request->session()->get('impersonator_id'))
                    ? ['par' => \App\Models\User::find($id)?->name]
                    : null,
            ],
            // Messages flash : au même niveau que "auth" (lus par FlashToast via page.props.flash)
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'missionSummary' => fn () => $request->session()->get('missionSummary'),
            ],
            // Liste des sièges { SGK: 'Siège Garage Kagnan', ... } disponible dans tous les composants Vue
            'sieges' => config('sieges'),
        ];
    }
}