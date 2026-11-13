<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Models\User;
use App\Notifications\ErreurApplication;
use App\Support\ErrorReporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Journal des erreurs de l'application, réservé au super administrateur. */
class ErreurController extends Controller
{
    public function index(Request $request)
    {
        $statut = in_array($request->input('statut'), ['ouvertes', 'resolues', 'toutes'], true) ? $request->input('statut') : 'ouvertes';

        $erreurs = ErrorLog::query()
            ->when($statut === 'ouvertes', fn ($q) => $q->whereNull('resolue_at'))
            ->when($statut === 'resolues', fn ($q) => $q->whereNotNull('resolue_at'))
            ->with(['user:id,name,role,is_superadmin', 'resolveur:id,name'])
            ->orderByDesc('derniere_fois')
            ->paginate(25, ['id', 'source', 'type', 'message', 'fichier', 'ligne', 'url', 'method', 'user_id', 'occurrences', 'premiere_fois', 'derniere_fois', 'resolue_at', 'resolue_par'])
            ->withQueryString();

        // Ouvrir la page = avoir pris connaissance des alertes
        $request->user()->unreadNotifications()->where('type', ErreurApplication::class)->update(['read_at' => now()]);

        return Inertia::render('SuperAdmin/Erreurs', [
            'erreurs' => $erreurs,
            'statut' => $statut,
            'compteurs' => [
                'ouvertes' => ErrorLog::whereNull('resolue_at')->count(),
                'aujourdhui' => ErrorLog::where('derniere_fois', '>=', now()->startOfDay())->count(),
            ],
        ]);
    }

    public function show(ErrorLog $erreur)
    {
        return response()->json(['erreur' => $erreur->load(['user:id,name,role', 'resolveur:id,name'])]);
    }

    public function resoudre(Request $request, ErrorLog $erreur)
    {
        $erreur->update(['resolue_at' => now(), 'resolue_par' => $request->user()->id]);

        return back()->with('success', 'Erreur marquée comme résolue.');
    }

    public function rouvrir(ErrorLog $erreur)
    {
        $erreur->update(['resolue_at' => null, 'resolue_par' => null]);

        return back()->with('success', 'Erreur rouverte.');
    }

    /** Cloche du super administrateur : nouvelles erreurs + personnes en ligne (polling). */
    public function alertes(Request $request)
    {
        $notifications = $request->user()
            ->unreadNotifications()
            ->where('type', ErreurApplication::class)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($n) => ['id' => $n->id, 'data' => $n->data, 'created_at' => $n->created_at]);

        return response()->json([
            'notifications' => $notifications,
            'erreurs_ouvertes' => ErrorLog::whereNull('resolue_at')->count(),
            'en_ligne' => User::where('last_seen_at', '>', now()->subMinutes(2))->count(),
        ]);
    }

    public function marquerLue(Request $request, string $notification)
    {
        $request->user()->notifications()
            ->where('type', ErreurApplication::class)
            ->whereKey($notification)
            ->firstOrFail()
            ->markAsRead();

        return response()->noContent();
    }

    /** Erreurs JavaScript remontées par le navigateur de n'importe quel utilisateur connecté. */
    public function client(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'max:250'],
            'fichier' => ['nullable', 'string', 'max:1000'],
            'ligne' => ['nullable', 'integer'],
            'trace' => ['nullable', 'string', 'max:20000'],
            'url' => ['nullable', 'string', 'max:1000'],
        ]);

        ErrorReporter::capturerClient($data);

        return response()->noContent();
    }
}
