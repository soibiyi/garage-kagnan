<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPortalRequestRejection extends Model
{
    protected $fillable = [
        'client_portal_request_id',
        'chauffeur_id',
    ];
}
