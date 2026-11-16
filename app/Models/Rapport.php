<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rapport quotidien ou hebdomadaire du super administrateur. */
class Rapport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['periode', 'debut', 'fin', 'donnees', 'genere_par'];

    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'fin' => 'datetime',
            'donnees' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }
}
