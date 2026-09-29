<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intervention extends Model
{
    use HasFactory;

    protected $table = 'interventions';

    protected $guarded = [];

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

    public function mecanicien()
    {
        return $this->belongsTo(User::class, 'mecanicien_id');
    }

    public function client()
    {
        return $this->hasOneThrough(Client::class, Vehicule::class, 'id', 'id', 'vehicule_id', 'client_id');
    }

    public function devis()
    {
        return $this->hasOne(Devis::class, 'intervention_id');
    }

    /**
     * Relation avec la facture liée à l'intervention
     */
    public function facture()
    {
        return $this->hasOne(Facture::class, 'intervention_id');
    }
}