<?php

namespace App\Http\Controllers;

use App\Models\SecretaireTache;
use App\Models\SecretaireTacheItem;
use App\Models\User;
use App\Notifications\SecretaireTacheTerminee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

/**
 * Espace de la secrétaire : checklist des tâches reçues, triée par délai.
 */
class SecretaireController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $actives = SecretaireTache::query()
            ->where('secretaire_id', $user->id)
            ->where('statut', '!=', 'terminee')
            ->with(['items', 'admin:id,name'])
            ->orderBy('echeance')
            ->get();

        // Historique récent : tâches terminées ces 30 derniers jours
        $terminees = SecretaireTache::query()
            ->where('secretaire_id', $user->id)
            ->where('statut', 'terminee')
            ->where('terminee_at', '>=', now()->subDays(30))
            ->with(['items', 'admin:id,name'])
            ->latest('terminee_at')
            ->limit(50)
            ->get();

        return Inertia::render('Secretaire/Index', [
            'taches' => $actives,
            'terminees' => $terminees,
        ]);
    }

    /** Coche / décoche une étape de la checklist. */
    public function toggleItem(Request $request, SecretaireTacheItem $item)
    {
        $tache = $this->tacheDe($request, $item->tache);

        $item->update([
            'fait' => ! $item->fait,
            'fait_at' => $item->fait ? null : now(),
        ]);

        if ($tache->synchroniserStatut()) {
            $this->notifierAdmins($tache, $request->user());
        }

        return back();
    }

    /** La secrétaire ajoute sa propre étape à une tâche (pour s'organiser). */
    public function storeItem(Request $request, SecretaireTache $tache)
    {
        $this->tacheDe($request, $tache);

        $data = $request->validate(['libelle' => ['required', 'string', 'max:255']]);

        $tache->items()->create([
            'libelle' => $data['libelle'],
            'ordre' => (int) $tache->items()->max('ordre') + 1,
            'ajoute_par_secretaire' => true,
        ]);
        $tache->synchroniserStatut();

        return back();
    }

    /** Supprime une étape qu'elle a elle-même ajoutée (celles de l'admin restent). */
    public function destroyItem(Request $request, SecretaireTacheItem $item)
    {
        $tache = $this->tacheDe($request, $item->tache);
        abort_unless($item->ajoute_par_secretaire, 403, "Cette étape a été fixée par l'administrateur.");

        $item->delete();
        if ($tache->synchroniserStatut()) {
            $this->notifierAdmins($tache, $request->user());
        }

        return back();
    }

    /** Change le statut d'une tâche (utile surtout pour les tâches sans étapes). */
    public function updateStatut(Request $request, SecretaireTache $tache)
    {
        $this->tacheDe($request, $tache);

        $data = $request->validate(['statut' => ['required', 'in:a_faire,en_cours,terminee']]);

        if ($data['statut'] === 'terminee' && $tache->items()->where('fait', false)->exists()) {
            return back()->with('error', 'Cochez toutes les étapes avant de terminer la tâche.');
        }

        $etaitTerminee = $tache->statut === 'terminee';
        $tache->update([
            'statut' => $data['statut'],
            'terminee_at' => $data['statut'] === 'terminee' ? ($tache->terminee_at ?? now()) : null,
        ]);

        if (! $etaitTerminee && $data['statut'] === 'terminee') {
            $this->notifierAdmins($tache, $request->user());
        }

        return back();
    }

    private function tacheDe(Request $request, SecretaireTache $tache): SecretaireTache
    {
        abort_unless($tache->secretaire_id === $request->user()->id, 403);

        return $tache;
    }

    private function notifierAdmins(SecretaireTache $tache, User $secretaire): void
    {
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new SecretaireTacheTerminee($tache->fresh(), $secretaire->name));
    }
}
