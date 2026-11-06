<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPortalRequestPosition extends Model
{
    protected $fillable = [
        'client_portal_request_id',
        'latitude',
        'longitude',
    ];

    public function request()
    {
        return $this->belongsTo(ClientPortalRequest::class, 'client_portal_request_id');
    }
}
