<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DemandeSuppression;
use App\Models\Intervention;
use App\Support\Suppressions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/** Validation par un administrateur des demandes de suppression faites par les collaborateurs. */
class DemandeSuppressionController extends Controller
{
    /** Présentation d'une demande pour le front (page dédiée et carte du tableau de bord). */
    public static function presenter(DemandeSuppression $d): array
    {
        return [
            'id' => $d->id,
            'type' => DemandeSuppression::TYPES[$d->type] ?? $d->type,
            'libelle' => $d->libelle,
            'contexte' => $d->contexte ?: null,
            'motif' => $d->motif,
            'siege' => $d->siege,
            'statut' => $d->statut,
            'reponse' => $d->reponse,
            'date' => $d->created_at,
            'demandeur' => $d->demandeur ? ['name' => $d->demandeur->name, 'role' => $d->demandeur->role] : null,
            'traite_par' => $d->traitePar?->name,
            'traite_le' => $d->traite_le,
            'existe' => Intervention::whereKey($d->cible_id)->exists(),
        ];
    }

    public function index()
    {
        $presenter = fn (DemandeSuppression $d) => self::presenter($d);

        return Inertia::render('Admin/DemandesSuppression', [
            'enAttente' => DemandeSuppression::enAttente()->with('demandeur:id,name,role')->oldest('id')->get()->map($presenter),
            'traitees' => DemandeSuppression::where('statut', '!=', 'en_attente')->with(['demandeur:id,name,role', 'traitePar:id,name'])
                ->latest('traite_le')->limit(30)->get()->map($presenter),
        ]);
    }

    public function approuver(Request $request, DemandeSuppression $demande)
    {
        if ($demande->statut !== 'en_attente') {
            return back()->with('error', 'Cette demande a déjà été traitée.');
        }

        $dossier = Intervention::find($demande->cible_id);
        if (! $dossier) {
            $this->clore($demande, $request, 'caduque', 'Élément déjà supprimé.');

            return back()->with('success', 'Cet élément avait déjà été supprimé : demande close.');
        }

        try {
            Suppressions::executer($demande->type, $dossier);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->clore($demande, $request, 'approuvee', $request->input('reponse'));

        return back()->with('success', "{$demande->libelle} supprimé. Le super administrateur peut le restaurer si besoin.");
    }

    public function refuser(Request $request, DemandeSuppression $demande)
    {
        $request->validate(['reponse' => ['nullable', 'string', 'max:500']]);

        if ($demande->statut !== 'en_attente') {
            return back()->with('error', 'Cette demande a déjà été traitée.');
        }

        $this->clore($demande, $request, 'refusee', $request->input('reponse'));

        return back()->with('success', 'Demande refusée : rien n\'a été supprimé.');
    }

    public function approuverSelection(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
            'reponse' => ['nullable', 'string', 'max:500'],
        ]);

        $demandes = DemandeSuppression::enAttente()->whereIn('id', $data['ids'])->get();
        $approuvees = 0;

        foreach ($demandes as $demande) {
            $dossier = Intervention::find($demande->cible_id);
            if (! $dossier) {
                $this->clore($demande, $request, 'caduque', 'Élément déjà supprimé.');
                $approuvees++;
                continue;
            }

            try {
                Suppressions::executer($demande->type, $dossier);
                $this->clore($demande, $request, 'approuvee', $data['reponse'] ?? null);
                $approuvees++;
            } catch (RuntimeException) {
                // Une demande incompatible reste en attente afin de pouvoir la traiter séparément.
            }
        }

        if (! $approuvees) {
            return back()->with('error', 'Aucune demande n’a pu être approuvée.');
        }

        return back()->with('success', "{$approuvees} demande(s) traitée(s) avec succès.");
    }

    public function refuserSelection(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
            'reponse' => ['nullable', 'string', 'max:500'],
        ]);

        $demandes = DemandeSuppression::enAttente()->whereIn('id', $data['ids'])->get();

        foreach ($demandes as $demande) {
            $this->clore($demande, $request, 'refusee', $data['reponse'] ?? null);
        }

        if (! $demandes->count()) {
            return back()->with('error', 'Aucune demande en attente n’a été trouvée.');
        }

        return back()->with('success', "{$demandes->count()} demande(s) refusée(s).");
    }

    private function clore(DemandeSuppression $demande, Request $request, string $statut, ?string $reponse): void
    {
        $demande->update([
            'statut' => $statut,
            'traite_par' => $request->user()->id,
            'traite_le' => now(),
            'reponse' => $reponse ? mb_substr($reponse, 0, 500) : null,
        ]);
    }
}
