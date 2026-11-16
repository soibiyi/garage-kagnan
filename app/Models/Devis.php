<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devis extends Model
{
    use \App\Models\Concerns\Audite;

    protected $guarded = [];

    protected $casts = [
        'is_complementaire' => 'boolean',
    ];

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
