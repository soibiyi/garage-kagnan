<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    public function facture()
{
    return $this->belongsTo(Facture::class);
}

public function enregistrePar()
{
    return $this->belongsTo(User::class, 'enregistre_par');
}
}
