<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'nombre_voitures',
        'email',
        'adresse',
        'type_client',
    ];
}