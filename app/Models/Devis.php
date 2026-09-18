<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devis extends Model
{
    public function intervention()
{
    return $this->belongsTo(Intervention::class);
}

public function lignes()
{
    return $this->hasMany(LigneDevis::class);
}

public function createur()
{
    return $this->belongsTo(User::class, 'createur_id');
}
}
