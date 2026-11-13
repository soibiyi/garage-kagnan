<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecretaireMessage;
use App\Models\SecretaireTache;
use App\Models\User;
use App\Notifications\SecretaireTacheAssignee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Espace administrateur du secrétariat : attribution des tâches (avec délai et étapes)
 * et suivi de leur avancement par secrétaire.
 */
class SecretariatController extends Controller
{
    private const FILTRES = ['actives', 'terminees', 'toutes'];

    public function index(Request $request)
    {
        $secretaires = User::query()
            ->where('role', 'secretaire')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'siege'])
            ->map(fn (User $s) => [
                ...$s->only(['id', 'name', 'email', 'siege']),
                'taches_ouvertes' => SecretaireTache::where('secretaire_id', $s->id)->where('statut', '!=', 'terminee')->count(),
                'taches_en_retard' => SecretaireTache::where('secretaire_id', $s->id)
                    ->where('statut', '!=', 'terminee')
                    ->where('echeance', '<', now())
                    ->count(),
                'messages_non_lus' => SecretaireMessage::where('secretaire_id', $s->id)
                    ->where('sender_id', $s->id)
                    ->whereNull('lu_at')
                    ->count(),
            ]);

        $selection = $secretaires->firstWhere('id', (int) $request->input('secretaire')) ?? $secretaires->first();
        $filtre = in_array($request->input('filtre'), self::FILTRES, true) ? $request->input('filtre') : 'actives';

        $taches = $selection
            ? SecretaireTache::query()
                ->where('secretaire_id', $selection['id'])
                ->when($filtre === 'actives', fn ($q) => $q->where('statut', '!=', 'terminee'))
                ->when($filtre === 'terminees', fn ($q) => $q->where('statut', 'terminee'))
                ->with(['items', 'admin:id,name'])
                ->orderByRaw("CASE WHEN statut = 'terminee' THEN 1 ELSE 0 END")
                ->orderBy($filtre === 'terminees' ? 'terminee_at' : 'echeance', $filtre === 'terminees' ? 'desc' : 'asc')
                ->limit(200)
                ->get()
            : collect();

        return Inertia::render('Admin/Secretariat/Index', [
            'secretaires' => $secretaires->values(),
            'selection' => $selection,
            'taches' => $taches,
            'filtre' => $filtre,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->valider($request);

        $tache = DB::transaction(function () use ($data, $request) {
            $tache = SecretaireTache::create([
                'secretaire_id' => $data['secretaire_id'],
                'admin_id' => $request->user()->id,
                'titre' => $data['titre'],
                'instructions' => $data['instructions'] ?? null,
                'echeance' => $data['echeance'],
                'priorite' => $data['priorite'],
                'statut' => 'a_faire',
            ]);

            foreach (array_values($data['etapes'] ?? []) as $i => $etape) {
                $tache->items()->create(['libelle' => $etape['libelle'], 'ordre' => $i]);
            }

            return $tache;
        });

        $tache->secretaire->notify(new SecretaireTacheAssignee($tache));

        return redirect()
            ->route('admin.secretariat.index', ['secretaire' => $tache->secretaire_id])
            ->with('success', 'Tâche attribuée à ' . $tache->secretaire->name . '.');
    }

    public function update(Request $request, SecretaireTache $tache)
    {
        $data = $this->valider($request);

        DB::transaction(function () use ($data, $tache) {
            $tache->update([
                'secretaire_id' => $data['secretaire_id'],
                'titre' => $data['titre'],
                'instructions' => $data['instructions'] ?? null,
                'echeance' => $data['echeance'],
                'priorite' => $data['priorite'],
            ]);

            // Étapes : on conserve celles renvoyées (avec leur état coché), on crée les nouvelles,
            // on supprime celles retirées par l'administrateur.
            $conservees = [];
            foreach (array_values($data['etapes'] ?? []) as $i => $etape) {
                $item = isset($etape['id'])
                    ? $tache->items()->whereKey($etape['id'])->first()
                    : null;

                if ($item) {
                    $item->update(['libelle' => $etape['libelle'], 'ordre' => $i]);
                } else {
                    $item = $tache->items()->create(['libelle' => $etape['libelle'], 'ordre' => $i]);
                }
                $conservees[] = $item->id;
            }
            $tache->items()->whereNotIn('id', $conservees)->delete();

            if (empty($conservees) && $tache->statut === 'terminee') {
                // Plus de checklist : on garde l'état « terminée » choisi par la secrétaire.
                return;
            }
            $tache->synchroniserStatut();
        });

        $tache->refresh()->secretaire->notify(new SecretaireTacheAssignee($tache, modification: true));

        return redirect()
            ->route('admin.secretariat.index', ['secretaire' => $tache->secretaire_id])
            ->with('success', 'Tâche mise à jour.');
    }

    public function destroy(SecretaireTache $tache)
    {
        $secretaireId = $tache->secretaire_id;
        $tache->delete();

        return redirect()
            ->route('admin.secretariat.index', ['secretaire' => $secretaireId])
            ->with('success', 'Tâche supprimée.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'secretaire_id' => ['required', Rule::exists('users', 'id')->where('role', 'secretaire')],
            'titre' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'echeance' => ['required', 'date'],
            'priorite' => ['required', Rule::in(SecretaireTache::PRIORITES)],
            'etapes' => ['nullable', 'array', 'max:50'],
            'etapes.*.id' => ['nullable', 'integer'],
            'etapes.*.libelle' => ['required', 'string', 'max:255'],
        ], [
            'secretaire_id.exists' => 'Veuillez choisir une secrétaire.',
            'echeance.required' => 'Le délai est obligatoire.',
            'etapes.*.libelle.required' => 'Une étape ne peut pas être vide.',
        ]);
    }
}
