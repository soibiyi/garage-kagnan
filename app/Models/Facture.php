<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facture extends Model
{
    public function intervention()
{
    return $this->belongsTo(Intervention::class);
}

public function devis()
{
    return $this->belongsTo(Devis::class);
}

public function paiements()
{
    return $this->hasMany(Paiement::class);
}
}
