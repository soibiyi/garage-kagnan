<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicule extends Model
{
    use HasFactory;

    protected $table = 'vehicules';

    protected $fillable = [
        'client_id', 'immatriculation', 'marque', 'modele', 'vin', 'expiration_assurance', 'expiration_sicta', 'kilometrage'
    ];

    protected $casts = [
        'expiration_assurance' => 'date:Y-m-d',
        'expiration_sicta' => 'date:Y-m-d',
        'kilometrage' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function interventions()
    {
        return $this->hasMany(Intervention::class);
    }

    public function reparationsExternes()
    {
        return $this->hasMany(ReparationExterne::class)->orderByDesc('date_reparation')->orderByDesc('id');
    }

    // Suivi des interactions avec le client au sujet de ce véhicule
    public function interactions()
    {
        return $this->hasMany(InteractionClient::class);
    }
}