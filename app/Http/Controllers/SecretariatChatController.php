<?php

namespace App\Http\Controllers;

use App\Models\SecretaireMessage;
use App\Models\SecretaireTache;
use App\Models\User;
use App\Notifications\SecretaireTacheAssignee;
use App\Notifications\SecretaireTacheTerminee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Messagerie admin ↔ secrétaire et notifications du secrétariat (JSON, interrogé en polling).
 * Une conversation par secrétaire ; tous les administrateurs y participent.
 */
class SecretariatChatController extends Controller
{
    public function messages(Request $request, User $secretaire)
    {
        $this->autoriser($request, $secretaire);

        $after = (int) $request->input('after', 0);

        $messages = SecretaireMessage::query()
            ->where('secretaire_id', $secretaire->id)
            ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
            ->with(['sender:id,name,role', 'tache:id,titre'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        // Ouvrir la conversation = lire les messages de l'autre partie
        $this->messagesNonLusPour($request->user(), $secretaire)->update(['lu_at' => now()]);

        return response()->json([
            'messages' => $messages->map(fn (SecretaireMessage $m) => $this->presenter($m, $request->user())),
        ]);
    }

    public function store(Request $request, User $secretaire)
    {
        $this->autoriser($request, $secretaire);

        $data = $request->validate([
            'contenu' => ['required', 'string', 'max:3000'],
            'tache_id' => [
                'nullable',
                Rule::exists('secretaire_taches', 'id')->where('secretaire_id', $secretaire->id),
            ],
        ]);

        $message = SecretaireMessage::create([
            'secretaire_id' => $secretaire->id,
            'sender_id' => $request->user()->id,
            'tache_id' => $data['tache_id'] ?? null,
            'contenu' => trim($data['contenu']),
        ])->load(['sender:id,name,role', 'tache:id,titre']);

        return response()->json(['message' => $this->presenter($message, $request->user())], 201);
    }

    public function notifications(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'secretaire'], true), 403);

        $type = $user->role === 'admin' ? SecretaireTacheTerminee::class : SecretaireTacheAssignee::class;

        $notifications = $user->unreadNotifications()
            ->where('type', $type)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($n) => ['id' => $n->id, 'data' => $n->data, 'created_at' => $n->created_at]);

        if ($user->role === 'admin') {
            // Messages non lus, par secrétaire
            $nonLus = SecretaireMessage::query()
                ->whereNull('lu_at')
                ->whereColumn('sender_id', 'secretaire_id')
                ->selectRaw('secretaire_id, COUNT(*) as total')
                ->groupBy('secretaire_id')
                ->pluck('total', 'secretaire_id');

            $messagesNonLus = $nonLus->sum();
            $parSecretaire = $nonLus;
        } else {
            $messagesNonLus = $this->messagesNonLusPour($user, $user)->count();
            $parSecretaire = collect();
        }

        return response()->json([
            'notifications' => $notifications,
            'messages_non_lus' => (int) $messagesNonLus,
            'messages_par_secretaire' => $parSecretaire,
            'taches_en_retard' => $user->role === 'secretaire'
                ? SecretaireTache::where('secretaire_id', $user->id)
                    ->where('statut', '!=', 'terminee')
                    ->where('echeance', '<', now())
                    ->count()
                : null,
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        $request->user()
            ->notifications()
            ->whereIn('type', [SecretaireTacheAssignee::class, SecretaireTacheTerminee::class])
            ->whereKey($notification)
            ->firstOrFail()
            ->markAsRead();

        return response()->noContent();
    }

    private function autoriser(Request $request, User $secretaire): void
    {
        abort_unless($secretaire->role === 'secretaire', 404);

        $user = $request->user();
        abort_unless($user->role === 'admin' || $user->id === $secretaire->id, 403);
    }

    private function messagesNonLusPour(User $lecteur, User $secretaire)
    {
        return SecretaireMessage::query()
            ->where('secretaire_id', $secretaire->id)
            ->whereNull('lu_at')
            ->when(
                $lecteur->id === $secretaire->id,
                // La secrétaire lit les messages des administrateurs
                fn ($q) => $q->where(fn ($s) => $s->whereNull('sender_id')->orWhere('sender_id', '!=', $secretaire->id)),
                // L'administrateur lit les messages de la secrétaire
                fn ($q) => $q->where('sender_id', $secretaire->id)
            );
    }

    private function presenter(SecretaireMessage $m, User $lecteur): array
    {
        return [
            'id' => $m->id,
            'contenu' => $m->contenu,
            'created_at' => $m->created_at,
            'lu_at' => $m->lu_at,
            'est_moi' => $m->sender_id === $lecteur->id,
            'sender' => $m->sender ? ['id' => $m->sender->id, 'name' => $m->sender->name, 'role' => $m->sender->role] : null,
            'tache' => $m->tache ? ['id' => $m->tache->id, 'titre' => $m->tache->titre] : null,
        ];
    }
}
