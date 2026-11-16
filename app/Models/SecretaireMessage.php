<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretaireMessage extends Model
{
    protected $fillable = [
        'secretaire_id',
        'sender_id',
        'tache_id',
        'contenu',
        'piece_jointe_path',
        'piece_jointe_nom',
        'piece_jointe_mime',
        'piece_jointe_taille',
        'lu_at',
    ];

    protected function casts(): array
    {
        return [
            'lu_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(SecretaireTache::class, 'tache_id');
    }
}
