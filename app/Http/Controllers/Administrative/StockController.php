<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'fichier' => 'required|file|max:10240',
        ]);

        $file = $request->file('fichier');
        $handle = fopen($file->getRealPath(), 'r');

        // Ignorer la ligne des en-têtes
        fgetcsv($handle, 1000, ';');

        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            if (!empty($data[2])) { // La désignation de la pièce est obligatoire
                Stock::updateOrCreate(
                    ['reference' => !empty($data[3]) ? $data[3] : null],
                    [
                        'marque'            => $data[0] ?? null,
                        'modele'            => $data[1] ?? null,
                        'designation_piece' => $data[2],
                        'prix_kagnan_ht'    => $data[4] ?? null,
                    ]
                );
            }
        }

        fclose($handle);

        return redirect()->back()->with('success', 'Importation et mise à jour du stock effectuées avec succès.');
    }
}