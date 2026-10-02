<?php

namespace App\Imports;

use App\Models\Stock;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StocksImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (empty($row['designation_piece'])) {
            return null;
        }

        // Met à jour si la référence existe déjà, sinon crée une nouvelle entrée
        return Stock::updateOrCreate(
            ['reference' => $row['reference'] ?? null],
            [
                'marque'            => $row['marque'] ?? null,
                'modele'            => $row['modele'] ?? null,
                'designation_piece' => $row['designation_piece'],
                'prix_kagnan_ht'    => $row['prix_kagnan_ht'] ?? null,
            ]
        );
    }
}