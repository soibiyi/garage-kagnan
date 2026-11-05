<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPortalRequest extends Model
{
    protected $fillable = [
        'client_id',
        'vehicule_id',
        'siege',
        'type',
        'details',
        'statut',
        'traite_par',
        'traite_le',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'traite_le' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
