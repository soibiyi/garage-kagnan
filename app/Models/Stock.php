<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $table = 'stocks';

    protected $guarded = [];

    public $timestamps = false; // Garde ton comportement d'origine si tu n'as que created_at

    // Relation avec les 3 sièges
    public function sieges()
    {
        return $this->hasMany(StockSiege::class);
    }

    // Total des sorties cumulées sur tous les sièges
    public function getTotalSortiesAttribute()
    {
        return $this->sieges()->sum('sortie');
    }

    // Stock restant global (Quantité initiale - Total des sorties)
    public function getStockRestantGlobalAttribute()
    {
        return max(0, ($this->quantite ?? 0) - $this->total_sorties);
    }
}