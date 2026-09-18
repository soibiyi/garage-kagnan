<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response|RedirectResponse
    {
        // Si l'utilisateur est déjà connecté, on l'empêche de voir le login
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->role === 'admin') {
                return redirect()->route('admin.users.index');
            }
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Récupération de l'utilisateur connecté
        $user = auth()->user();

        // Redirection dynamique selon le rôle
        if ($user->role === 'admin') {
            return redirect()->intended(route('admin.users.index', absolute: false));
        }

        // Pour les autres rôles (réceptionniste, mécanicien, etc.)
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}