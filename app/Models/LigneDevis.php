<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LigneDevis extends Model
{
    public function devis()
{
    return $this->belongsTo(Devis::class);
}
}
