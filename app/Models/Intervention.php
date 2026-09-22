<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intervention extends Model
{
    use HasFactory;

    protected $table = 'interventions';

    protected $guarded = []; // Ou listez vos champs de remplissage

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
}