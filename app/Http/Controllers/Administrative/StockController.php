<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\MvtStock;
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
                  ->orWhere('famille', 'like', "%{$search}%")
                  ->orWhere('marque', 'like', "%{$search}%")
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
                  ->orWhere('famille', 'like', "%{$search}%")
                  ->orWhere('marque', 'like', "%{$search}%")
                  ->orWhere('vehicule_correspondant', 'like', "%{$search}%");
            });
        }

        $stocks = $query->with('sieges')->orderBy('designation_piece', 'asc')->paginate(25)->withQueryString();

        return Inertia::render('Magasinier/Pieces/Index', [
            'stocks' => $stocks,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Historique global des mouvements (Entrées / Sorties) avec onglets
     */
    public function historiqueMouvements(Request $request)
    {
        $query = MvtStock::with(['stock', 'user']);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('siege')) {
            $query->where('siege', $request->input('siege'));
        }

        $mouvements = $query->latest()->paginate(25)->withQueryString();

        return Inertia::render('Administration/Stocks/HistoriqueMouvements', [
            'mouvements' => $mouvements,
            'filters' => $request->only(['type', 'siege'])
        ]);
    }

    public function magasinierUpdate(Request $request, Stock $stock)
    {
        $validated = $request->validate([
            'sieges' => 'required|array',
            'sieges.*.siege' => 'required|string|in:Terminus 27,Zone Industrielle,Yamoussoukro',
            'sieges.*.quantite_attribuee' => 'required|integer|min:0',
            'sieges.*.sortie' => 'required|integer|min:0',
            'motif' => 'required|string|max:255',
        ]);

        $totalAttribue = collect($validated['sieges'])->sum('quantite_attribuee');
        if ($totalAttribue > ($stock->quantite ?? 0)) {
            return redirect()->back()->withErrors([
                'sieges' => "La somme des attributions par siège ({$totalAttribue}) ne peut pas dépasser la quantité initiale du lot ({$stock->quantite})."
            ]);
        }

        DB::transaction(function () use ($validated, $stock, $request) {
            foreach ($validated['sieges'] as $ligne) {
                $ancienSiege = $stock->sieges()->where('siege', $ligne['siege'])->first();
                $ancienneSortie = $ancienSiege ? $ancienSiege->sortie : 0;
                $nouvelleSortie = $ligne['sortie'];

                if ($nouvelleSortie < $ancienneSortie) {
                    throw new \Exception("Il est interdit de modifier ou réduire une sortie déjà validée pour le siège {$ligne['siege']}.");
                }

                if ($nouvelleSortie > $ancienneSortie) {
                    $difference = $nouvelleSortie - $ancienneSortie;
                    MvtStock::create([
                        'stock_id' => $stock->id,
                        'user_id' => auth()->id(),
                        'type' => 'sortie',
                        'quantite' => $difference,
                        'siege' => $ligne['siege'],
                        'motif' => $request->input('motif'),
                    ]);
                }

                $stock->sieges()->updateOrCreate(
                    ['siege' => $ligne['siege']],
                    [
                        'quantite_attribuee' => $ligne['quantite_attribuee'],
                        'sortie' => $nouvelleSortie,
                    ]
                );
            }
        });

        return redirect()->back()->with('success', 'Mouvements et répartitions enregistrés avec succès.');
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

        DB::transaction(function () use ($validated, $siegesData, &$stock) {
            $stock = Stock::create($validated);

            if (($stock->quantite ?? 0) > 0) {
                MvtStock::create([
                    'stock_id' => $stock->id,
                    'user_id' => auth()->id(),
                    'type' => 'entree',
                    'quantite' => $stock->quantite,
                    'motif' => 'Création / Réception de stock initial',
                ]);
            }

            if (!empty($siegesData)) {
                foreach ($siegesData as $ligne) {
                    $stock->sieges()->create([
                        'siege' => $ligne['siege'],
                        'quantite_attribuee' => $ligne['quantite_attribuee'] ?? 0,
                        'sortie' => $ligne['sortie'] ?? 0,
                    ]);
                }
            }
        });

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

        $ancienneQuantite = $stock->quantite;
        $quantiteTotale = $validated['quantite'] ?? $ancienneQuantite;

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

        DB::transaction(function () use ($validated, $stock, $ancienneQuantite, $quantiteTotale, $siegesData) {
            $stock->update($validated);

            if ($quantiteTotale > $ancienneQuantite) {
                $difference = $quantiteTotale - $ancienneQuantite;
                MvtStock::create([
                    'stock_id' => $stock->id,
                    'user_id' => auth()->id(),
                    'type' => 'entree',
                    'quantite' => $difference,
                    'motif' => 'Réapprovisionnement / Mise à jour du stock',
                ]);
            }

            if ($siegesData !== null) {
                foreach ($siegesData as $ligne) {
                    $ancienSiege = $stock->sieges()->where('siege', $ligne['siege'])->first();
                    $ancienneSortie = $ancienSiege ? $ancienSiege->sortie : 0;
                    $nouvelleSortie = $ligne['sortie'] ?? 0;

                    if ($nouvelleSortie < $ancienneSortie) {
                        throw new \Exception("Il est interdit de modifier ou réduire une sortie déjà validée.");
                    }

                    if ($nouvelleSortie > $ancienneSortie) {
                        MvtStock::create([
                            'stock_id' => $stock->id,
                            'user_id' => auth()->id(),
                            'type' => 'sortie',
                            'quantite' => $nouvelleSortie - $ancienneSortie,
                            'siege' => $ligne['siege'],
                            'motif' => 'Sortie atelier (Mise à jour)',
                        ]);
                    }

                    $stock->sieges()->updateOrCreate(
                        ['siege' => $ligne['siege']],
                        [
                            'quantite_attribuee' => $ligne['quantite_attribuee'] ?? 0,
                            'sortie' => $nouvelleSortie,
                        ]
                    );
                }
            }
        });

        return redirect()->back()->with('success', 'Stock mis à jour avec succès.');
    }

    public function destroy(Stock $stock)
    {
        $stock->delete();
        return redirect()->back()->with('success', 'Pièce supprimée du stock.');
    }

    public function export(): StreamedResponse
    {
        $fileName = 'catalogue-stocks-complet-' . date('Y-m-d') . '.csv';

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
                'Date Entree', 'Reference', 'Designation Piece', 'Famille', 'Sous Famille',
                'Marque', 'Modele', 'Prix Kagnan HT', 'Quantite Initiale', 'Prix Achat',
                'Marge Vente', 'Terminus 27 (Attribuee)', 'Terminus 27 (Sortie)',
                'Zone Industrielle (Attribuee)', 'Zone Industrielle (Sortie)',
                'Yamoussoukro (Attribuee)', 'Yamoussoukro (Sortie)',
            ], ';');

            Stock::with('sieges')->orderBy('id')->chunk(200, function ($stocks) use ($handle) {
                foreach ($stocks as $stock) {
                    $t27 = $stock->sieges->where('siege', 'Terminus 27')->first();
                    $zi  = $stock->sieges->where('siege', 'Zone Industrielle')->first();
                    $yam = $stock->sieges->where('siege', 'Yamoussoukro')->first();

                    fputcsv($handle, [
                        $stock->date_entree ?? '', $stock->reference ?? '', $stock->designation_piece ?? '',
                        $stock->famille ?? '', $stock->sous_famille ?? '', $stock->marque ?? '',
                        $stock->modele ?? '', $stock->prix_kagnan_ht ?? '', $stock->quantite ?? 0,
                        $stock->prix_achat ?? '', $stock->marge_vente ?? '',
                        $t27 ? $t27->quantite_attribuee : 0, $t27 ? $t27->sortie : 0,
                        $zi ? $zi->quantite_attribuee : 0, $zi ? $zi->sortie : 0,
                        $yam ? $yam->quantite_attribuee : 0, $yam ? $yam->sortie : 0,
                    ], ';');
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    public function import(Request $request)
    {
        $request->validate([
            'fichier' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'fichier.mimes' => 'Le fichier doit être au format Excel (.xlsx, .xls) ou CSV.',
        ]);

        try {
            $lignes = $this->lireLignesImport($request->file('fichier'));
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['fichier' => 'Impossible de lire le fichier : ' . $e->getMessage()]);
        }

        $crees = 0;
        $misAJour = 0;
        $ignores = 0;

        DB::beginTransaction();
        try {
            foreach ($lignes as $data) {
                if (empty($data[2])) { $ignores++; continue; }

                $reference = trim((string) ($data[1] ?? ''));

                $stock = Stock::updateOrCreate(
                    ['reference' => $reference ?: Str::random(10)],
                    [
                        'date_entree'       => $this->normaliserDate($data[0] ?? null),
                        'designation_piece' => trim((string) $data[2]),
                        'famille'           => trim((string) ($data[3] ?? '')) ?: null,
                        'sous_famille'      => trim((string) ($data[4] ?? '')) ?: null,
                        'marque'            => trim((string) ($data[5] ?? '')) ?: null,
                        'modele'            => trim((string) ($data[6] ?? '')) ?: null,
                        'prix_kagnan_ht'    => trim((string) ($data[7] ?? '')) ?: null,
                        'quantite'          => (int) ($data[8] ?? 0),
                        'prix_achat'        => is_numeric($data[9] ?? null) ? $data[9] : null,
                        'marge_vente'       => is_numeric($data[10] ?? null) ? $data[10] : null,
                    ]
                );

                if ($stock->wasRecentlyCreated) {
                    $crees++;
                    if (($stock->quantite ?? 0) > 0) {
                        MvtStock::create([
                            'stock_id' => $stock->id,
                            'user_id'  => auth()->id(),
                            'type'     => 'entree',
                            'quantite' => $stock->quantite,
                            'motif'    => 'Import de fichier (stock initial)',
                        ]);
                    }
                } else {
                    $misAJour++;
                }

                $sieges = [
                    'Terminus 27'       => ['attrib' => (int) ($data[11] ?? 0), 'sortie' => (int) ($data[12] ?? 0)],
                    'Zone Industrielle' => ['attrib' => (int) ($data[13] ?? 0), 'sortie' => (int) ($data[14] ?? 0)],
                    'Yamoussoukro'      => ['attrib' => (int) ($data[15] ?? 0), 'sortie' => (int) ($data[16] ?? 0)],
                ];

                foreach ($sieges as $nomSiege => $qte) {
                    $stock->sieges()->updateOrCreate(
                        ['siege' => $nomSiege],
                        ['quantite_attribuee' => $qte['attrib'], 'sortie' => $qte['sortie']]
                    );
                }
            }

            DB::commit();
            return redirect()->back()->with('success',
                "Import terminé : {$crees} pièce(s) créée(s), {$misAJour} mise(s) à jour, {$ignores} ligne(s) ignorée(s)."
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['fichier' => 'Erreur : ' . $e->getMessage()]);
        }
    }

    /**
     * Lit un fichier Excel (.xlsx/.xls) ou CSV et renvoie les lignes de données
     * (sans la ligne d'en-têtes), sous forme de tableaux indexés par position.
     */
    private function lireLignesImport($fichier): array
    {
        $extension = strtolower($fichier->getClientOriginalExtension());
        $path = $fichier->getRealPath();

        if (in_array($extension, ['xlsx', 'xls'])) {
            $feuille = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            // valeurs brutes (pas de formatage), tableau indexé 0,1,2...
            $lignes = $feuille->toArray(null, true, false, false);
            array_shift($lignes); // ligne d'en-têtes
            return $lignes;
        }

        // CSV : comportement d'origine (détection du séparateur ; ou ,)
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d’ouvrir le fichier.');
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") { rewind($handle); }
        $firstLine = fgets($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") { fread($handle, 3); }
        fgetcsv($handle, 0, $delimiter); // en-têtes

        $lignes = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lignes[] = $data;
        }
        fclose($handle);

        return $lignes;
    }

    private function normaliserDate($valeur): ?string
    {
        if ($valeur === null || $valeur === '') return null;

        // Date Excel stockée sous forme de nombre (numéro de série)
        if (is_numeric($valeur) && (float) $valeur > 20000) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($valeur)->format('Y-m-d');
        }

        $timestamp = strtotime((string) $valeur);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    public function reapprovisionnement(Request $request)
    {
        $stocks = Stock::with('sieges')
            ->get()
            ->filter(function ($stock) {
                $totalSorties = $stock->sieges->sum('sortie');
                return ($stock->quantite - $totalSorties) <= 5;
            })
            ->values();

        return Inertia::render('Administration/Stocks/Reapprovisionnement', ['stocks' => $stocks]);
    }

    public function genererBonCommande(Request $request)
    {
        $validated = $request->validate([
            'fournisseur_nom' => 'required|string|max:150',
            'fournisseur_contact' => 'nullable|string|max:100',
            'articles' => 'required|array|min:1',
            'articles.*.designation' => 'required|string',
            'articles.*.reference' => 'nullable|string',
            'articles.*.quantite_a_commander' => 'required|integer|min:1',
            'articles.*.prix_estime' => 'nullable|numeric',
        ]);

        return Inertia::render('Administration/Stocks/BonCommandePdf', [
            'commande' => [
                'numero' => 'BC-' . date('Ymd-His'),
                'date' => date('d/m/Y'),
                'fournisseur_nom' => $validated['fournisseur_nom'],
                'fournisseur_contact' => $validated['fournisseur_contact'] ?? '',
                'articles' => $validated['articles'],
            ]
        ]);
    }
}