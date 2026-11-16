<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Inventaire;
use App\Models\StockSiege;
use App\Models\User;
use App\Support\Annulation;
use App\Support\Audit;
use App\Support\ConflitAnnulation;
use App\Support\ExportExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

/**
 * Contrôle et anti-fraude :
 *  - actions sensibles (alertes) et historique avant / après des modèles critiques ;
 *  - écarts d'inventaire (stock théorique vs comptage physique).
 */
class ControleController extends Controller
{
    public function index(Request $request)
    {
        $onglet = in_array($request->query('onglet'), ['alertes', 'historique', 'inventaire'], true) ? $request->query('onglet') : 'alertes';

        return Inertia::render('SuperAdmin/Controle', [
            'onglet' => $onglet,
            'filtres' => $request->only(['type', 'siege', 'user', 'q', 'evenement']),
            'journal' => $onglet === 'inventaire' ? null : $this->journal($request, $onglet === 'alertes'),
            'compteurs' => [
                'alertes_7j' => AuditLog::whereNotNull('alerte')->where('created_at', '>=', now()->subDays(7))->count(),
                'alertes_aujourdhui' => AuditLog::whereNotNull('alerte')->where('created_at', '>=', now()->startOfDay())->count(),
                'modifications_aujourdhui' => AuditLog::where('created_at', '>=', now()->startOfDay())->count(),
            ],
            'types' => Audit::MODELES,
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
            'inventaire' => $onglet === 'inventaire' ? $this->inventaire($request) : null,
        ]);
    }

    private function requeteJournal(Request $request, bool $alertesSeulement)
    {
        return AuditLog::query()
            ->when($alertesSeulement, fn ($q) => $q->whereNotNull('alerte'))
            ->when($request->query('type'), fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($request->query('siege'), fn ($q, $s) => $q->where('siege', $s))
            ->when($request->query('user'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('evenement'), fn ($q, $e) => $q->where('evenement', $e))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('libelle', 'like', "%{$s}%")->orWhere('alerte', 'like', "%{$s}%")->orWhere('contexte', 'like', "%{$s}%")));
    }

    private function journal(Request $request, bool $alertesSeulement)
    {
        $page = $this->requeteJournal($request, $alertesSeulement)
            ->with(['user:id,name,role', 'impersonator:id,name', 'annulePar:id,name'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        // Nombre d'actions encore annulables dans chaque opération affichée
        $lots = collect($page->items())->pluck('lot')->filter()->unique()->values();
        $tailles = $lots->isEmpty() ? [] : AuditLog::query()->whereIn('lot', $lots)->whereNull('annule_le')
            ->selectRaw('lot, COUNT(*) AS n')->groupBy('lot')->pluck('n', 'lot')->all();

        return $page->through(fn (AuditLog $a) => $this->presenter($a, $tailles));
    }

    private function presenter(AuditLog $a, array $tailles = []): array
    {
        // Anciennes traces : client / véhicule reconstitués une fois, puis mémorisés
        if ($a->contexte === null && in_array($a->auditable_type, ['Facture', 'Paiement', 'Devis', 'LigneDevis', 'Intervention'], true)) {
            $contexte = Audit::contexteDuJournal($a);
            $a->forceFill(['contexte' => $contexte ?: []])->saveQuietly();
        }

        $champs = array_unique(array_merge(array_keys($a->avant ?? []), array_keys($a->apres ?? [])));

        return [
            'id' => $a->id,
            'date' => $a->created_at,
            'auteur' => $a->user?->name ?? 'Système',
            'role' => $a->user?->role,
            'par_superadmin' => $a->impersonator?->name,
            'type' => Audit::MODELES[$a->auditable_type] ?? $a->auditable_type,
            'libelle' => $a->libelle,
            'evenement' => $a->evenement,
            'siege' => $a->siege,
            'alerte' => $a->alerte,
            'ip' => $a->ip,
            'contexte' => $a->contexte ?: null,
            'annulable' => ! $a->annule_le && isset(Audit::CLASSES[$a->auditable_type]),
            'operation' => $a->lot ? max(1, (int) ($tailles[$a->lot] ?? 1)) : 1,
            'annulation' => $a->annule_le ? ['date' => $a->annule_le, 'par' => $a->annulePar?->name] : null,
            'changements' => array_values(array_map(fn ($c) => [
                'champ' => $c,
                'avant' => $a->avant[$c] ?? null,
                'apres' => $a->apres[$c] ?? null,
            ], $champs)),
        ];
    }

    public function export(Request $request)
    {
        $alertes = $request->query('onglet') !== 'historique';
        $lignes = $this->requeteJournal($request, $alertes)
            ->with(['user:id,name', 'impersonator:id,name'])
            ->latest('id')->limit(5000)->get()
            ->map(fn (AuditLog $a) => [
                'date' => $a->created_at?->format('d/m/Y H:i'),
                'auteur' => $a->user?->name ?? 'Système',
                'par' => $a->impersonator?->name,
                'type' => Audit::MODELES[$a->auditable_type] ?? $a->auditable_type,
                'libelle' => $a->libelle,
                'client' => $a->contexte['client'] ?? null,
                'vehicule' => trim(($a->contexte['vehicule'] ?? '') . ' ' . ($a->contexte['immatriculation'] ?? '')) ?: null,
                'ot' => $a->contexte['numero_ot'] ?? null,
                'evenement' => $a->evenement,
                'siege' => $a->siege,
                'alerte' => $a->alerte,
                'annulee' => $a->annule_le?->format('d/m/Y H:i'),
                'details' => collect(array_unique(array_merge(array_keys($a->avant ?? []), array_keys($a->apres ?? []))))
                    ->map(fn ($c) => "{$c} : " . json_encode($a->avant[$c] ?? null, JSON_UNESCAPED_UNICODE) . ' → ' . json_encode($a->apres[$c] ?? null, JSON_UNESCAPED_UNICODE))
                    ->implode(' | '),
            ])->all();

        return ExportExcel::telecharger($alertes ? 'actions-sensibles' : 'historique-modifications', 'Export du ' . now()->format('d/m/Y H:i'), [[
            'titre' => $alertes ? 'Actions sensibles' : 'Historique des modifications',
            'colonnes' => ['date' => 'Date', 'auteur' => 'Auteur', 'par' => 'Via super admin', 'type' => 'Type', 'libelle' => 'Élément',
                'client' => 'Client', 'vehicule' => 'Véhicule', 'ot' => 'N° OT', 'evenement' => 'Événement', 'siege' => 'Siège', 'alerte' => 'Alerte', 'annulee' => 'Annulée le', 'details' => 'Avant → après'],
            'lignes' => $lignes,
        ]]);
    }

    /** Annule une action (et toute l'opération dont elle fait partie). */
    public function annuler(Request $request, AuditLog $audit)
    {
        $forcer = $request->boolean('forcer');

        try {
            $n = Annulation::annuler($audit, $request->user(), $forcer);
        } catch (ConflitAnnulation $e) {
            return back()->withErrors(['conflit' => implode("\n", $e->conflits)]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $n > 1
            ? "Opération annulée : {$n} actions défaites."
            : 'Action annulée.');
    }

    // ─── Inventaire ──────────────────────────────────────────────────

    private function inventaire(Request $request): array
    {
        $siege = array_key_exists((string) $request->query('siege'), config('sieges')) ? $request->query('siege') : array_key_first(config('sieges'));
        $recherche = trim((string) $request->query('q'));

        $derniers = Inventaire::query()
            ->whereIn('id', Inventaire::query()->where('siege', $siege)->selectRaw('MAX(id)')->groupBy('stock_id'))
            ->get()->keyBy('stock_id');

        $pieces = StockSiege::query()
            ->join('stocks', 'stocks.id', '=', 'stock_sieges.stock_id')
            ->where('stock_sieges.siege', $siege)
            ->where('stock_sieges.quantite_attribuee', '>', 0)
            ->when($recherche, fn ($q) => $q->where(fn ($w) => $w
                ->where('stocks.designation_piece', 'like', "%{$recherche}%")
                ->orWhere('stocks.reference', 'like', "%{$recherche}%")
                ->orWhere('stocks.marque', 'like', "%{$recherche}%")))
            ->orderBy('stocks.designation_piece')
            ->select(['stocks.id AS stock_id', 'stocks.designation_piece', 'stocks.reference', 'stocks.marque', 'stocks.modele', 'stocks.prix_achat',
                DB::raw('GREATEST(stock_sieges.quantite_attribuee - stock_sieges.sortie, 0) AS theorique')])
            ->paginate(25)
            ->withQueryString()
            ->through(function ($p) use ($derniers) {
                $dernier = $derniers->get($p->stock_id);

                return [
                    'stock_id' => $p->stock_id,
                    'piece' => $p->designation_piece ?: '—',
                    'reference' => $p->reference,
                    'vehicule' => trim(($p->marque ?? '') . ' ' . ($p->modele ?? '')),
                    'prix_achat' => (float) $p->prix_achat,
                    'theorique' => (int) $p->theorique,
                    'dernier_comptage' => $dernier ? [
                        'date' => $dernier->created_at,
                        'comptee' => $dernier->quantite_comptee,
                        'ecart' => $dernier->ecart,
                    ] : null,
                ];
            });

        $ecarts = Inventaire::query()
            ->where('ecart', '!=', 0)
            ->with(['stock:id,designation_piece,reference', 'user:id,name'])
            ->latest('id')->limit(30)->get()
            ->map(fn (Inventaire $i) => [
                'id' => $i->id,
                'date' => $i->created_at,
                'siege' => $i->siege,
                'piece' => $i->stock?->designation_piece ?: '—',
                'reference' => $i->stock?->reference,
                'theorique' => $i->quantite_theorique,
                'comptee' => $i->quantite_comptee,
                'ecart' => $i->ecart,
                'valeur' => $i->valeur_ecart,
                'commentaire' => $i->commentaire,
                'par' => $i->user?->name,
            ]);

        $depuis = now()->subDays(30);

        return [
            'siege' => $siege,
            'pieces' => $pieces,
            'ecarts' => $ecarts,
            'resume' => [
                'comptages_30j' => Inventaire::where('created_at', '>=', $depuis)->count(),
                'manquants_30j' => (int) Inventaire::where('created_at', '>=', $depuis)->where('ecart', '<', 0)->sum(DB::raw('-ecart')),
                'valeur_manquante_30j' => (float) Inventaire::where('created_at', '>=', $depuis)->where('ecart', '<', 0)->sum(DB::raw('-valeur_ecart')),
                'surplus_30j' => (int) Inventaire::where('created_at', '>=', $depuis)->where('ecart', '>', 0)->sum('ecart'),
            ],
        ];
    }

    /** Enregistre un comptage physique (une ou plusieurs pièces d'un siège). */
    public function compter(Request $request)
    {
        $data = $request->validate([
            'siege' => ['required', 'in:' . implode(',', array_keys(config('sieges')))],
            'comptages' => ['required', 'array', 'min:1', 'max:100'],
            'comptages.*.stock_id' => ['required', 'integer', 'exists:stocks,id'],
            'comptages.*.quantite_comptee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'comptages.*.commentaire' => ['nullable', 'string', 'max:250'],
        ]);

        $ecarts = 0;
        DB::transaction(function () use ($data, $request, &$ecarts) {
            foreach ($data['comptages'] as $c) {
                $ligne = StockSiege::query()
                    ->where('stock_id', $c['stock_id'])->where('siege', $data['siege'])
                    ->with('stock:id,prix_achat')->first();
                $theorique = $ligne ? max(0, $ligne->quantite_attribuee - $ligne->sortie) : 0;
                $ecart = (int) $c['quantite_comptee'] - $theorique;
                $ecarts += $ecart !== 0 ? 1 : 0;

                Inventaire::create([
                    'stock_id' => $c['stock_id'],
                    'siege' => $data['siege'],
                    'quantite_theorique' => $theorique,
                    'quantite_comptee' => $c['quantite_comptee'],
                    'ecart' => $ecart,
                    'valeur_ecart' => $ecart * (float) ($ligne?->stock?->prix_achat ?? 0),
                    'commentaire' => $c['commentaire'] ?? null,
                    'user_id' => $request->user()->id,
                ]);
            }
        });

        $n = count($data['comptages']);

        return back()->with('success', "{$n} comptage(s) enregistré(s)" . ($ecarts ? ", dont {$ecarts} avec écart." : ', aucun écart.'));
    }
}
