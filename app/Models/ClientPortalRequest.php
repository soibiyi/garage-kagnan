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
        'chauffeur_id',
        'chauffeur_accepted_at',
        'mission_started_at',
        'mission_completed_at',
        'client_lat',
        'client_lng',
        'destination_lat',
        'destination_lng',
        'gps_consent_at',
        'prix_estime',
        'tarif_reference',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'traite_le' => 'datetime',
            'chauffeur_accepted_at' => 'datetime',
            'mission_started_at' => 'datetime',
            'mission_completed_at' => 'datetime',
            'gps_consent_at' => 'datetime',
            'client_lat' => 'float',
            'client_lng' => 'float',
            'destination_lat' => 'float',
            'destination_lng' => 'float',
            'prix_estime' => 'integer',
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

    public function chauffeur()
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function rejections()
    {
        return $this->hasMany(ClientPortalRequestRejection::class, 'client_portal_request_id');
    }

    public function position()
    {
        return $this->hasOne(ClientPortalRequestPosition::class, 'client_portal_request_id');
    }
}
