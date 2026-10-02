<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    protected $fillable = [
    'facture_id',
    'enregistre_par',
    'montant',
    'mode_paiement',
    'notes',
    'date_paiement',
];
    public function facture()
{
    return $this->belongsTo(Facture::class);
}

public function enregistrePar()
{
    return $this->belongsTo(User::class, 'enregistre_par');
}
}
