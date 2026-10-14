<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /** Statuts affichés en sous-onglets (dans cet ordre). */
    private const STATUTS = ['reception', 'atelier', 'en_cours', 'attente_accord', 'accepte'];

    public function index(Request $request)
    {
        // Les rôles sans tableau de bord sont renvoyés vers leur espace
        $role = $request->user()->role;

        if ($role === 'admin') {
            return redirect()->route('admin.users.index');
        }
        if ($role === 'magasinier') {
            return redirect()->route('magasinier.pieces.index');
        }

        $statuts = self::STATUTS;

        $type   = $request->input('type') === 'direct' ? 'direct' : 'normal';
        $statut = in_array($request->input('statut'), $statuts, true) ? $request->input('statut') : null;
        $search = trim((string) $request->input('search', ''));

        $interventionsAtelier = Intervention::duSiege()
            ->with(['vehicule.client', 'mecanicien'])
            // Devis direct = circuit 'devis_direct' ; tout le reste = circuit normal
            ->when(
                $type === 'direct',
                fn ($q) => $q->where('circuit', 'devis_direct'),
                fn ($q) => $q->where(fn ($s) => $s->whereNull('circuit')->orWhere('circuit', '!=', 'devis_direct'))
            )
            ->when(
                $statut,
                fn ($q) => $q->where('statut', $statut),
                fn ($q) => $q->whereIn('statut', $statuts)
            )
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($s) use ($search) {
                    $s->where('numero_ot', 'like', "%{$search}%")
                      ->orWhereHas('vehicule', function ($v) use ($search) {
                          $v->where('immatriculation', 'like', "%{$search}%")
                            ->orWhere('marque', 'like', "%{$search}%")
                            ->orWhere('modele', 'like', "%{$search}%")
                            ->orWhereHas('client', function ($c) use ($search) {
                                $c->where('nom', 'like', "%{$search}%")
                                  ->orWhere('prenom', 'like', "%{$search}%");
                            });
                      });
                });
            })
            ->latest('date_reception')
            ->paginate(10)
            ->withQueryString(); // garde type / statut / search dans les liens de pagination

        return Inertia::render('Dashboard', [
            'interventionsAtelier' => $interventionsAtelier,
            'filters'   => $request->only('search', 'statut', 'type'),
            'statuts'   => $statuts,
            'compteurs' => $this->compteurs($statuts),
        ]);
    }

    /**
     * Compteurs par type et par statut (indépendants de la recherche).
     * Format : ['normal' => ['tous' => 12, 'reception' => 3, ...], 'direct' => [...]]
     */
    private function compteurs(array $statuts): array
    {
        $vide = array_fill_keys([...$statuts, 'tous'], 0);
        $compteurs = ['normal' => $vide, 'direct' => $vide];

        $lignes = Intervention::duSiege()
            ->whereIn('statut', $statuts)
            ->selectRaw("(circuit = 'devis_direct') as est_direct, statut, COUNT(*) as n")
            ->groupBy('est_direct', 'statut')
            ->get();

        foreach ($lignes as $r) {
            $t = $r->est_direct ? 'direct' : 'normal';
            $compteurs[$t][$r->statut] = (int) $r->n;
            $compteurs[$t]['tous'] += (int) $r->n;
        }

        return $compteurs;
    }
}