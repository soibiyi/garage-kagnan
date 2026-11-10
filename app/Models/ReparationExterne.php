<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReparationExterne extends Model
{
    protected $table = 'vehicule_reparations_externes';

    protected $fillable = [
        'date_reparation',
        'lieu',
        'description',
        'kilometrage',
        'montant',
    ];

    protected $casts = [
        'date_reparation' => 'date:Y-m-d',
        'montant' => 'decimal:2',
    ];

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class);
    }
}
