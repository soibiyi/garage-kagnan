<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPortalMessage extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'sender_type',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
