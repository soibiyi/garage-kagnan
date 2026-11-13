<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretaireTacheItem extends Model
{
    protected $fillable = [
        'tache_id',
        'libelle',
        'fait',
        'fait_at',
        'ordre',
        'ajoute_par_secretaire',
    ];

    protected function casts(): array
    {
        return [
            'fait' => 'boolean',
            'ajoute_par_secretaire' => 'boolean',
            'fait_at' => 'datetime',
        ];
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(SecretaireTache::class, 'tache_id');
    }
}
