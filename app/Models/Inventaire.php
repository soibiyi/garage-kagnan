<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Comptage physique d'une pièce dans un siège : écart entre stock théorique et stock réel. */
class Inventaire extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['stock_id', 'siege', 'quantite_theorique', 'quantite_comptee', 'ecart', 'valeur_ecart', 'commentaire', 'user_id'];

    protected function casts(): array
    {
        return [
            'valeur_ecart' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
