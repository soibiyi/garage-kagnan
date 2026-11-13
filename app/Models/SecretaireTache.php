<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecretaireTache extends Model
{
    public const PRIORITES = ['basse', 'normale', 'haute'];
    public const STATUTS = ['a_faire', 'en_cours', 'terminee'];

    protected $fillable = [
        'secretaire_id',
        'admin_id',
        'titre',
        'instructions',
        'echeance',
        'priorite',
        'statut',
        'terminee_at',
    ];

    protected $appends = ['en_retard'];

    protected function casts(): array
    {
        return [
            'echeance' => 'datetime',
            'terminee_at' => 'datetime',
        ];
    }

    public function secretaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretaire_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SecretaireTacheItem::class, 'tache_id')->orderBy('ordre')->orderBy('id');
    }

    public function getEnRetardAttribute(): bool
    {
        return $this->statut !== 'terminee' && $this->echeance?->isPast();
    }

    /**
     * Recalcule le statut à partir de la checklist :
     * toutes les étapes cochées → terminée ; au moins une → en cours ; aucune → à faire.
     * Retourne true si la tâche vient de passer à « terminée ».
     */
    public function synchroniserStatut(): bool
    {
        $items = $this->items()->get();
        $total = $items->count();
        $faits = $items->where('fait', true)->count();

        if ($total === 0) {
            return false;
        }

        $ancien = $this->statut;
        $nouveau = $faits === $total ? 'terminee' : ($faits > 0 ? 'en_cours' : 'a_faire');

        $this->statut = $nouveau;
        $this->terminee_at = $nouveau === 'terminee' ? ($this->terminee_at ?? now()) : null;
        $this->save();

        return $ancien !== 'terminee' && $nouveau === 'terminee';
    }
}
