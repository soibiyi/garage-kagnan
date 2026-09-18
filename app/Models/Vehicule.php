<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicule extends Model
{
    use HasFactory;

    protected $table = 'vehicules';

    protected $fillable = [
        'client_id', 'immatriculation', 'marque', 'modele', 'vin', 'expiration_assurance', 'expiration_sicta'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function interventions()
    {
        return $this->hasMany(Intervention::class);
    }
}