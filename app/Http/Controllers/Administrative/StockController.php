<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $query = Stock::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('designation_piece', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('marque', 'like', "%{$search}%")
                  ->orWhere('modele', 'like', "%{$search}%");
            });
        }

        $stocks = $query->with('sieges')->orderBy('designation_piece', 'asc')->paginate(25)->withQueryString();

        return Inertia::render('Administration/Stocks/Index', [
            'stocks' => $stocks,
            'filters' => $request->only(['search'])
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = Stock::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('designation_piece', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('vehicule_correspondant', 'like', "%{$search}%");
            });
        }

        $stocks = $query->with('sieges')->orderBy('designation_piece', 'asc')->paginate(25)->withQueryString();

        return Inertia::render('Admin/Pieces/Index', [
            'stocks' => $stocks,
            'filters' => $request->only(['search'])
        ]);
    }

    public function magasinierIndex(Request $request)
    {
        $query = Stock::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('designation_piece', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('vehicule_correspondant', 'like', "%{$search}%");
            });
        }

        $stocks = $query->with('sieges')->orderBy('designation_piece', 'asc')->paginate(25)->withQueryString();

        return Inertia::render('Magasinier/Pieces/Index', [
            'stocks' => $stocks,
            'filters' => $request->only(['search'])
        ]);
    }

    public function magasinierUpdate(Request $request, Stock $stock)
    {
        $validated = $request->validate([
            'sieges' => 'required|array',
            'sieges.*.siege' => 'required|string|in:Terminus 27,Zone Industrielle,Yamoussoukro',
            'sieges.*.quantite_attribuee' => 'required|integer|min:0',
            'sieges.*.sortie' => 'required|integer|min:0',
        ]);

        // Vérification 1 : La somme des attributions ne dépasse pas le stock initial global
        $totalAttribue = collect($validated['sieges'])->sum('quantite_attribuee');
        if ($totalAttribue > ($stock->quantite ?? 0)) {
            return redirect()->back()->withErrors([
                'sieges' => "La somme des attributions par siège ({$totalAttribue}) ne peut pas dépasser la quantité initiale du lot ({$stock->quantite})."
            ]);
        }

        // Vérification 2 : Les sorties ne dépassent pas les quantités attribuées par siège
        foreach ($validated['sieges'] as $ligne) {
            if ($ligne['sortie'] > $ligne['quantite_attribuee']) {
                return redirect()->back()->withErrors([
                    'sieges' => "Pour le siège {$ligne['siege']}, les sorties ({$ligne['sortie']}) ne peuvent pas dépasser la quantité attribuée ({$ligne['quantite_attribuee']})."
                ]);
            }
        }

        foreach ($validated['sieges'] as $ligne) {
            $stock->sieges()->updateOrCreate(
                ['siege' => $ligne['siege']],
                [
                    'quantite_attribuee' => $ligne['quantite_attribuee'],
                    'sortie' => $ligne['sortie'],
                ]
            );
        }

        return redirect()->back()->with('success', 'Mouvements et répartitions par siège enregistrés avec succès.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'marque' => 'nullable|string|max:50',
            'modele' => 'nullable|string|max:100',
            'generation' => 'nullable|string|max:50',
            'moteur' => 'nullable|string|max:50',
            'categorie' => 'nullable|string|max:100',
            'famille' => 'nullable|string|max:100',
            'sous_famille' => 'nullable|string|max:100',
            'designation_piece' => 'required|string|max:200',
            'reference' => 'nullable|string|max:50',
            'periodicite' => 'nullable|string|max:50',
            'prix_kagnan_ht' => 'nullable|string|max:50',
            'prix_marche_ht' => 'nullable|numeric',
            'ecart_pourcent' => 'nullable|numeric',
            'prix_ttc_kagnan' => 'nullable|numeric',
            'note' => 'nullable|string|max:255',
            'date_entree' => 'nullable|date',
            'quantite' => 'nullable|integer|min:0',
            'vehicule_correspondant' => 'nullable|string|max:150',
            'prix_achat' => 'nullable|numeric|min:0',
            'marge_vente' => 'nullable|numeric|min:0',
            'sieges' => 'nullable|array',
            'sieges.*.siege' => 'required_with:sieges|string|in:Terminus 27,Zone Industrielle,Yamoussoukro',
            'sieges.*.quantite_attribuee' => 'nullable|integer|min:0',
            'sieges.*.sortie' => 'nullable|integer|min:0',
        ]);

        if (isset($validated['quantite']) && isset($validated['sieges'])) {
            $totalAttribue = collect($validated['sieges'])->sum('quantite_attribuee');
            if ($totalAttribue > $validated['quantite']) {
                return redirect()->back()->withErrors([
                    'sieges' => "La somme des attributions par siège ({$totalAttribue}) ne peut pas dépasser la quantité initiale du lot ({$validated['quantite']})."
                ]);
            }
        }

        $siegesData = $validated['sieges'] ?? [];
        unset($validated['sieges']);

        $stock = Stock::create($validated);

        if (!empty($siegesData)) {
            foreach ($siegesData as $ligne) {
                $stock->sieges()->create([
                    'siege' => $ligne['siege'],
                    'quantite_attribuee' => $ligne['quantite_attribuee'] ?? 0,
                    'sortie' => $ligne['sortie'] ?? 0,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Pièce ajoutée au stock avec succès.');
    }

    public function update(Request $request, Stock $stock)
    {
        $validated = $request->validate([
            'marque' => 'nullable|string|max:50',
            'modele' => 'nullable|string|max:100',
            'generation' => 'nullable|string|max:50',
            'moteur' => 'nullable|string|max:50',
            'categorie' => 'nullable|string|max:100',
            'famille' => 'nullable|string|max:100',
            'sous_famille' => 'nullable|string|max:100',
            'designation_piece' => 'required|string|max:200',
            'reference' => 'nullable|string|max:50',
            'periodicite' => 'nullable|string|max:50',
            'prix_kagnan_ht' => 'nullable|string|max:50',
            'prix_marche_ht' => 'nullable|numeric',
            'ecart_pourcent' => 'nullable|numeric',
            'prix_ttc_kagnan' => 'nullable|numeric',
            'note' => 'nullable|string|max:255',
            'date_entree' => 'nullable|date',
            'quantite' => 'nullable|integer|min:0',
            'vehicule_correspondant' => 'nullable|string|max:150',
            'prix_achat' => 'nullable|numeric|min:0',
            'marge_vente' => 'nullable|numeric|min:0',
            'sieges' => 'nullable|array',
            'sieges.*.siege' => 'required_with:sieges|string|in:Terminus 27,Zone Industrielle,Yamoussoukro',
            'sieges.*.quantite_attribuee' => 'nullable|integer|min:0',
            'sieges.*.sortie' => 'nullable|integer|min:0',
        ]);

        $quantiteTotale = $validated['quantite'] ?? $stock->quantite;
        if (isset($validated['sieges'])) {
            $totalAttribue = collect($validated['sieges'])->sum('quantite_attribuee');
            if ($totalAttribue > $quantiteTotale) {
                return redirect()->back()->withErrors([
                    'sieges' => "La somme des attributions par siège ({$totalAttribue}) ne peut pas dépasser la quantité initiale du lot ({$quantiteTotale})."
                ]);
            }
        }

        $siegesData = $validated['sieges'] ?? null;
        unset($validated['sieges']);

        $stock->update($validated);

        if ($siegesData !== null) {
            foreach ($siegesData as $ligne) {
                $stock->sieges()->updateOrCreate(
                    ['siege' => $ligne['siege']],
                    [
                        'quantite_attribuee' => $ligne['quantite_attribuee'] ?? 0,
                        'sortie' => $ligne['sortie'] ?? 0,
                    ]
                );
            }
        }

        return redirect()->back()->with('success', 'Stock mis à jour avec succès.');
    }

    public function destroy(Stock $stock)
    {
        $stock->delete();

        return redirect()->back()->with('success', 'Pièce supprimée du stock.');
    }

    public function export(): StreamedResponse
    {
        $fileName = 'catalogue-stocks-' . date('Y-m-d') . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Marque', 'Modèle', 'Désignation Pièce', 'Référence',
                'Prix Kagnan HT', 'Famille', 'Sous-famille',
            ], ';');

            Stock::select('marque', 'modele', 'designation_piece', 'reference', 'prix_kagnan_ht', 'famille', 'sous_famille')
                ->orderBy('id')
                ->chunk(200, function ($stocks) use ($handle) {
                    foreach ($stocks as $stock) {
                        fputcsv($handle, [
                            $stock->marque,
                            $stock->modele,
                            $stock->designation_piece,
                            $stock->reference,
                            $stock->prix_kagnan_ht,
                            $stock->famille,
                            $stock->sous_famille,
                        ], ';');
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}