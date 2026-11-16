<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Excel simple des rapports du super administrateur.
 * Chaque feuille : ['titre' => ..., 'colonnes' => [cle => libellé], 'lignes' => [[cle => valeur]]].
 */
class ExportExcel
{
    public static function telecharger(string $nomFichier, string $sousTitre, array $feuilles): StreamedResponse
    {
        $classeur = new Spreadsheet();
        $classeur->removeSheetByIndex(0);

        foreach ($feuilles as $feuille) {
            $ws = $classeur->createSheet();
            $ws->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $feuille['titre']), 0, 31));

            $ws->setCellValue('A1', $feuille['titre']);
            $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $ws->setCellValue('A2', $sousTitre);
            $ws->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('6B6E70');

            $colonnes = array_values($feuille['colonnes']);
            $cles = array_keys($feuille['colonnes']);
            $ws->fromArray($colonnes, null, 'A4');
            $derniere = $ws->getHighestColumn();
            $ws->getStyle("A4:{$derniere}4")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $ws->getStyle("A4:{$derniere}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B0F19');

            $ligne = 5;
            foreach ($feuille['lignes'] as $donnees) {
                $ws->fromArray(array_map(fn ($c) => $donnees[$c] ?? '', $cles), null, "A{$ligne}", true);
                $ligne++;
            }

            foreach (range(1, count($cles)) as $i) {
                $ws->getColumnDimensionByColumn($i)->setAutoSize(true);
            }
            $ws->freezePane('A5');
        }

        $classeur->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($classeur) {
            (new Xlsx($classeur))->save('php://output');
        }, $nomFichier . '-' . now()->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
