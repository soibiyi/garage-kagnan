<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom', 'prenom', 'telephone', 'nombre_voitures', 'email', 'adresse', 'type_client', 'siege'
    ];

    public function vehicules()
    {
        return $this->hasMany(Vehicule::class);
    }
}