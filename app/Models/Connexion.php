<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historique des connexions des collaborateurs (réussies et échouées). */
class Connexion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'email', 'reussie', 'motif', 'hors_horaires', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return [
            'reussie' => 'boolean',
            'hors_horaires' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
