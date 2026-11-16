<?php

namespace App\Http\Controllers;

use App\Models\SecretaireMessage;
use App\Models\SecretaireTache;
use App\Models\User;
use App\Notifications\SecretaireTacheAssignee;
use App\Notifications\SecretaireTacheTerminee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Messagerie admin ↔ secrétaire et notifications du secrétariat (JSON, interrogé en polling).
 * Une conversation par secrétaire ; tous les administrateurs y participent.
 */
class SecretariatChatController extends Controller
{
    /** Taille maximale d'une pièce jointe (10 Mo). */
    private const TAILLE_MAX_KO = 10240;

    private const EXTENSIONS_AUTORISEES = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'csv', 'zip',
    ];

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
            'contenu' => ['nullable', 'required_without:fichier', 'string', 'max:3000'],
            'fichier' => [
                'nullable',
                'file',
                'max:'.self::TAILLE_MAX_KO,
                'mimes:'.implode(',', self::EXTENSIONS_AUTORISEES),
            ],
            'tache_id' => [
                'nullable',
                Rule::exists('secretaire_taches', 'id')->where('secretaire_id', $secretaire->id),
            ],
        ], [
            'contenu.required_without' => 'Écrivez un message ou joignez un fichier.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
            'fichier.mimes' => 'Format non accepté. Photos (JPG, PNG, WEBP, GIF) ou documents (PDF, Word, Excel, PowerPoint, TXT, CSV, ZIP).',
            'fichier.uploaded' => "Le fichier n'a pas pu être téléversé (taille maximale du serveur dépassée ?).",
        ]);

        $pieceJointe = [];
        if ($fichier = $request->file('fichier')) {
            $pieceJointe = [
                'piece_jointe_path' => $fichier->store('secretariat/chat/'.$secretaire->id, 'local'),
                'piece_jointe_nom' => mb_substr($fichier->getClientOriginalName(), 0, 255),
                'piece_jointe_mime' => $fichier->getMimeType(),
                'piece_jointe_taille' => $fichier->getSize(),
            ];
        }

        $contenu = trim((string) ($data['contenu'] ?? ''));

        $message = SecretaireMessage::create([
            'secretaire_id' => $secretaire->id,
            'sender_id' => $request->user()->id,
            'tache_id' => $data['tache_id'] ?? null,
            'contenu' => $contenu !== '' ? $contenu : null,
            ...$pieceJointe,
        ])->load(['sender:id,name,role', 'tache:id,titre']);

        return response()->json(['message' => $this->presenter($message, $request->user())], 201);
    }

    /** Liste de tous les fichiers échangés dans la conversation, du plus récent au plus ancien. */
    public function piecesJointes(Request $request, User $secretaire)
    {
        $this->autoriser($request, $secretaire);

        $fichiers = SecretaireMessage::query()
            ->where('secretaire_id', $secretaire->id)
            ->whereNotNull('piece_jointe_path')
            ->with(['sender:id,name,role', 'tache:id,titre'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (SecretaireMessage $m) => $this->presenter($m, $request->user()));

        return response()->json(['fichiers' => $fichiers]);
    }

    /** Télécharge / affiche la pièce jointe d'un message (participants de la conversation uniquement). */
    public function pieceJointe(Request $request, SecretaireMessage $message)
    {
        $secretaire = User::findOrFail($message->secretaire_id);
        $this->autoriser($request, $secretaire);

        abort_unless(
            $message->piece_jointe_path && Storage::disk('local')->exists($message->piece_jointe_path),
            404
        );

        $nom = $message->piece_jointe_nom ?: basename($message->piece_jointe_path);
        $headers = ['Content-Type' => $message->piece_jointe_mime ?: 'application/octet-stream'];

        // Les images et PDF s'ouvrent dans le navigateur ; le reste est téléchargé
        return $this->estAffichable($message) && ! $request->boolean('download')
            ? Storage::disk('local')->response($message->piece_jointe_path, $nom, $headers)
            : Storage::disk('local')->download($message->piece_jointe_path, $nom, $headers);
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
            'piece_jointe' => $m->piece_jointe_path ? [
                'nom' => $m->piece_jointe_nom,
                'mime' => $m->piece_jointe_mime,
                'taille' => $m->piece_jointe_taille,
                'est_image' => $this->estImage($m),
                'url' => route('secretariat.chat.piece-jointe', $m),
                'url_telechargement' => route('secretariat.chat.piece-jointe', ['message' => $m, 'download' => 1]),
            ] : null,
        ];
    }

    private function estImage(SecretaireMessage $m): bool
    {
        // Le SVG est exclu (peut contenir du script)
        return in_array($m->piece_jointe_mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    private function estAffichable(SecretaireMessage $m): bool
    {
        return $this->estImage($m) || $m->piece_jointe_mime === 'application/pdf';
    }
}
