<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $query = Stock::query();

        // Recherche par désignation, référence, marque ou modèle
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('designation_piece', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('marque', 'like', "%{$search}%")
                  ->orWhere('modele', 'like', "%{$search}%");
            });
        }

        $stocks = $query->orderBy('designation_piece', 'asc')->paginate(25)->withQueryString();

        return Inertia::render('Administration/Stocks/Index', [
            'stocks' => $stocks,
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'marque' => 'nullable|string|max:50',
            'modele' => 'nullable|string|max:100',
            'generation' => 'nullable|string|max:50',
            'moteur' => 'nullable|string|max:50',
            'categorie' => 'nullable|string|max:100',
            'designation_piece' => 'required|string|max:200',
            'reference' => 'nullable|string|max:50',
            'periodicite' => 'nullable|string|max:50',
            'prix_kagnan_ht' => 'nullable|string|max:50',
            'prix_marche_ht' => 'nullable|numeric',
            'ecart_pourcent' => 'nullable|numeric',
            'prix_ttc_kagnan' => 'nullable|numeric',
            'note' => 'nullable|string|max:255',
        ]);

        Stock::create($validated);

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
            'designation_piece' => 'required|string|max:200',
            'reference' => 'nullable|string|max:50',
            'periodicite' => 'nullable|string|max:50',
            'prix_kagnan_ht' => 'nullable|string|max:50',
            'prix_marche_ht' => 'nullable|numeric',
            'ecart_pourcent' => 'nullable|numeric',
            'prix_ttc_kagnan' => 'nullable|numeric',
            'note' => 'nullable|string|max:255',
        ]);

        $stock->update($validated);

        return redirect()->back()->with('success', 'Stock mis à jour avec succès.');
    }

    public function destroy(Stock $stock)
    {
        $stock->delete();

        return redirect()->back()->with('success', 'Pièce supprimée du stock.');
    }

    /**
     * Exportation directe du stock au format CSV (compatible Excel).
     */
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

        return response()->stream(function() {
            $handle = fopen('php://output', 'w');
            
            // Inclusion du BOM UTF-8 pour conserver les caractères spéciaux sous Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // En-têtes du fichier
            fputcsv($handle, ['Marque', 'Modèle', 'Désignation Pièce', 'Référence', 'Prix Kagnan HT'], ';');

            // Extraction optimisée des données
            Stock::select('marque', 'modele', 'designation_piece', 'reference', 'prix_kagnan_ht')
                ->chunk(200, function($stocks) use ($handle) {
                    foreach ($stocks as $stock) {
                        fputcsv($handle, [
                            $stock->marque,
                            $stock->modele,
                            $stock->designation_piece,
                            $stock->reference,
                            $stock->prix_kagnan_ht,
                        ], ';');
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Importation et mise à jour des pièces via fichier CSV.
     */
    public function import(Request $request)
{
    $request->validate([
        'fichier' => 'required|file|mimes:csv,txt|max:10240',
    ]);

    $handle = fopen($request->file('fichier')->getRealPath(), 'r');

    if ($handle === false) {
        return redirect()->back()->with('error', 'Impossible de lire le fichier.');
    }

    // Ignorer la ligne des en-têtes
    fgetcsv($handle, 0, ';');

    $crees = 0;
    $modifies = 0;
    $ignores = 0;

    DB::transaction(function () use ($handle, &$crees, &$modifies, &$ignores) {
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $designation = trim($data[2] ?? '');

            // La désignation est obligatoire
            if ($designation === '') {
                $ignores++;
                continue;
            }

            $reference = trim($data[3] ?? '');

            $values = [
                'marque'            => trim($data[0] ?? '') ?: null,
                'modele'            => trim($data[1] ?? '') ?: null,
                'designation_piece' => $designation,
                'prix_kagnan_ht'    => trim($data[4] ?? '') ?: null,
            ];

            if ($reference !== '') {
                // Même référence : mise à jour, sinon création
                $stock = Stock::updateOrCreate(['reference' => $reference], $values);
                $stock->wasRecentlyCreated ? $crees++ : $modifies++;
            } else {
                // Pas de référence : toujours un ajout
                Stock::create($values);
                $crees++;
            }
        }
    });

    fclose($handle);

    return redirect()->back()->with(
        'success',
        "Import terminé : {$crees} ajoutée(s), {$modifies} mise(s) à jour, {$ignores} ligne(s) ignorée(s)."
    );
}
}
