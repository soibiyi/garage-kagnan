<?php

namespace App\Exports;

use App\Models\Stock;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StocksExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Stock::select(
            'marque',
            'modele',
            'designation_piece',
            'reference',
            'prix_kagnan_ht'
        )->get();
    }

    public function headings(): array
    {
        return [
            'marque',
            'modele',
            'designation_piece',
            'reference',
            'prix_kagnan_ht',
        ];
    }
}