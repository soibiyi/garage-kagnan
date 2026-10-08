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
     * Colonnes : Marque, Modèle, Désignation Pièce, Référence, Prix Kagnan HT, Famille, Sous-famille.
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

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour conserver les caractères spéciaux sous Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // En-têtes du fichier
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

    /**
     * Importation et mise à jour des pièces via fichier CSV.
     * Les colonnes sont lues par leur nom d'en-tête (l'ordre n'a pas d'importance).
     */
    public function import(Request $request)
    {
        $request->validate([
            'fichier' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $content = file_get_contents($request->file('fichier')->getRealPath());

        if ($content === false || $content === '') {
            return redirect()->back()->with('error', 'Le fichier est vide ou illisible.');
        }

        // Fichiers "Unicode" d'Excel (UTF-16) => conversion en UTF-8
        if (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');
        }
        $content = ltrim($content, "\xEF\xBB\xBF"); // BOM UTF-8

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        // Détection du séparateur sur la première ligne non vide
        $firstLine = '';
        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') { $firstLine = $line; break; }
        }
        rewind($handle);
        $counts = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $delimiter = array_key_first($counts);

        // Variantes d'en-têtes acceptées pour chaque colonne
        $aliases = [
            'marque'            => ['marque', 'brand'],
            'modele'            => ['modele', 'model'],
            'designation_piece' => ['designation_piece', 'designation', 'designation_de_la_piece', 'designation_de_piece', 'piece', 'libelle', 'article'],
            'reference'         => ['reference', 'ref', 'refs'],
            'prix_kagnan_ht'    => ['prix_kagnan_ht', 'prix_kagnan', 'prix_ht', 'prix'],
            'famille'           => ['famille'],
            'sous_famille'      => ['sous_famille', 'sousfamille', 'sous_familles'],
        ];

        // Recherche de la ligne d'en-têtes dans les 10 premières lignes
        $index = [];
        $lastHeaders = [];
        for ($i = 0; $i < 10; $i++) {
            $row = $this->readRow($handle, $delimiter);
            if ($row === false) break;

            $normalized = array_map(
                fn ($h) => Str::slug($this->toUtf8((string) $h), '_'),
                $row
            );
            if (array_filter($normalized)) {
                $lastHeaders = $normalized;
            }

            $found = [];
            foreach ($aliases as $col => $names) {
                foreach ($normalized as $pos => $name) {
                    if (in_array($name, $names, true)) {
                        $found[$col] = $pos;
                        break;
                    }
                }
            }

            if (isset($found['designation_piece'])) {
                $index = $found;
                break;
            }
        }

        if (!isset($index['designation_piece'])) {
            fclose($handle);
            $lus = implode(' | ', array_filter($lastHeaders)) ?: 'aucun en-tête lisible';
            return redirect()->back()->with(
                'error',
                "Colonne « Désignation Pièce » introuvable. En-têtes lus dans votre fichier : {$lus}"
            );
        }

        $crees = 0;
        $modifies = 0;
        $ignores = 0;

        DB::transaction(function () use ($handle, $delimiter, $index, &$crees, &$modifies, &$ignores) {
            while (($data = $this->readRow($handle, $delimiter)) !== false) {
                if ($data === [null]) {
                    continue; // ligne vide
                }

                // Uniquement les colonnes présentes dans le fichier
                $values = [];
                foreach ($index as $col => $pos) {
                    $values[$col] = trim($this->toUtf8((string) ($data[$pos] ?? ''))) ?: null;
                }

                // La désignation est obligatoire
                if (empty($values['designation_piece'])) {
                    $ignores++;
                    continue;
                }

                $reference = $values['reference'] ?? null;

                if (!empty($reference)) {
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

    /**
     * Lit une ligne CSV. Si toute la ligne est dans une seule cellule
     * (cas d'un CSV ouvert puis réenregistré par Excel sans séparation en colonnes),
     * la ligne est redécoupée avec le séparateur détecté.
     */
    private function readRow($handle, string $delimiter)
    {
        $row = fgetcsv($handle, 0, $delimiter);

        if ($row === false) {
            return false;
        }

        if (count($row) === 1 && $row[0] !== null && substr_count($row[0], $delimiter) > 0) {
            return str_getcsv($row[0], $delimiter);
        }

        return $row;
    }

    /**
     * Convertit une valeur en UTF-8 (les CSV enregistrés par Excel sont souvent en Windows-1252).
     */
    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8')
            ? $value
            : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}