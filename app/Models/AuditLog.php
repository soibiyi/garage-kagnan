<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historique avant / après d'un modèle critique (facture, devis, paiement, stock…). */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'lot', 'user_id', 'impersonator_id', 'auditable_type', 'auditable_id', 'libelle', 'contexte',
        'evenement', 'avant', 'apres', 'siege', 'alerte', 'ip', 'annule_le', 'annule_par',
    ];

    protected function casts(): array
    {
        return [
            'avant' => 'array',
            'apres' => 'array',
            'contexte' => 'json:unicode', // accents lisibles en base : la recherche « Koné » fonctionne
            'created_at' => 'datetime',
            'annule_le' => 'datetime',
        ];
    }

    public function annulePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }
}
