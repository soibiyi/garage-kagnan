<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intervention extends Model
{
    use HasFactory;

    protected $table = 'interventions';

    protected $guarded = []; // Ou listez vos champs de remplissage
    
    protected $casts = [
        'date_reception' => 'datetime',
    ];

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function receptionniste()
    {
        return $this->belongsTo(User::class, 'receptionniste_id');
    }

    // Relation avec le mécanicien (table users)
    public function mecanicien()
    {
        return $this->belongsTo(User::class, 'mecanicien_id');
    }

    public function client()
    {
        return $this->hasOneThrough(Client::class, Vehicule::class, 'id', 'id', 'vehicule_id', 'client_id');
    }

    /**
     * Relation avec le ou les devis liés à cette intervention
     */
   public function devis()
{
    return $this->hasOne(Devis::class, 'intervention_id');
}
}