<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'siege',
    ];

    /** Rôles reconnus ; « superadmin » est stocké comme role = 'admin' + is_superadmin = true. */
    public const ROLES = ['admin', 'receptionniste', 'mecanicien', 'administratif', 'charge_client', 'magasinier', 'depanneur', 'depaneur', 'chauffeur', 'secretaire'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function chauffeurMissions()
    {
        return $this->hasMany(ClientPortalRequest::class, 'chauffeur_id');
    }

    /** Super administrateur : tous les droits admin + supervision (activité, erreurs, « voir en tant que »). */
    public function estSuperAdmin(): bool
    {
        return $this->role === 'admin' && (bool) $this->is_superadmin;
    }

    /** Libellé du rôle tel qu'affiché (le superadmin est un admin particulier). */
    public function getRoleAffichageAttribute(): string
    {
        return $this->estSuperAdmin() ? 'superadmin' : (string) $this->role;
    }

    /**
     * Comptes visibles par un utilisateur : le super administrateur (l'équipe de développement)
     * n'apparaît que pour un autre super administrateur.
     */
    public function scopeVisiblesPar($query, ?User $lecteur)
    {
        return $lecteur?->estSuperAdmin() ? $query : $query->where('is_superadmin', false);
    }

    public function estEnLigne(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(2));
    }
}
