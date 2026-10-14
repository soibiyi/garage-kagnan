<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockSiege extends Model
{
    use HasFactory;

    protected $table = 'stock_sieges';

    protected $fillable = [
        'stock_id',
        'siege',
        'quantite_attribuee',
        'sortie',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    // Calcul du stock restant pour ce siège spécifique
    public function getStockRestantAttribute()
    {
        return max(0, $this->quantite_attribuee - $this->sortie);
    }
}