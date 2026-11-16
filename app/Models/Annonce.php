<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bandeau d'annonce affiché en haut de l'application (à tous ou à certains rôles). */
class Annonce extends Model
{
    public const NIVEAUX = ['info', 'attention', 'urgent'];

    protected $fillable = ['titre', 'message', 'niveau', 'roles', 'debut', 'fin', 'active', 'auteur_id'];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'debut' => 'datetime',
            'fin' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /** Annonces actives maintenant. */
    public function scopeEnCours($query)
    {
        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('debut')->orWhere('debut', '<=', now()))
            ->where(fn ($q) => $q->whereNull('fin')->orWhere('fin', '>=', now()));
    }

    public function concerne(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if (empty($this->roles)) {
            return true;
        }
        $role = $user->role === 'depaneur' ? 'depanneur' : $user->role;

        return in_array($role, $this->roles, true) || $user->estSuperAdmin();
    }
}
