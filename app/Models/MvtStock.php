<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MvtStock extends Model
{
    use HasFactory;

    protected $table = 'mvt_stocks';

    protected $fillable = [
        'stock_id',
        'user_id',
        'type',
        'quantite',
        'siege',
        'motif',
        'commentaire',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}