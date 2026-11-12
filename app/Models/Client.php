<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Client extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'nom', 'prenom', 'telephone', 'nombre_voitures', 'email', 'adresse', 'type_client', 'siege'
    ];

    protected $hidden = [
        'portal_identifier',
        'portal_password',
        'portal_temp_password',
        'portal_password_change_required',
    ];

    protected function casts(): array
    {
        return [
            'portal_password_change_required' => 'boolean',
            'portal_registered' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Client $client): void {
            if (!$client->portal_identifier) {
                if ($client->portal_password) {
                    $client->forceFill([
                        'portal_identifier' => $client->newPortalIdentifier(),
                    ])->saveQuietly();
                } else {
                    $client->generatePortalCredentials();
                }
            }
        });
    }

    public function getAuthPasswordName(): string
    {
        return 'portal_password';
    }

    public function temporaryPortalPassword(): ?string
    {
        return $this->portal_temp_password
            ? Crypt::decryptString($this->portal_temp_password)
            : null;
    }

    public function generatePortalCredentials(): void
    {
        $temporaryPassword = Str::random(16);
        $identifier = $this->newPortalIdentifier();

        $this->forceFill([
            'portal_identifier' => $identifier,
            'portal_password' => Hash::make($temporaryPassword),
            'portal_temp_password' => Crypt::encryptString($temporaryPassword),
            'portal_password_change_required' => true,
        ])->saveQuietly();
    }

    public function replaceTemporaryPortalPassword(): void
    {
        $temporaryPassword = Str::random(16);

        $this->forceFill([
            'portal_password' => Hash::make($temporaryPassword),
            'portal_temp_password' => Crypt::encryptString($temporaryPassword),
            'portal_password_change_required' => true,
        ])->save();
    }

    private function newPortalIdentifier(): string
    {
        do {
            $identifier = 'GK-KEY-'.Str::upper(Str::random(6)).'-ID-'.$this->id;
        } while (self::where('portal_identifier', $identifier)->exists());

        return $identifier;
    }

    public function vehicules()
    {
        return $this->hasMany(Vehicule::class);
    }

    public function portalRequests()
    {
        return $this->hasMany(ClientPortalRequest::class);
    }

    public function consignes()
    {
        return $this->hasMany(Consigne::class)->latest();
    }

    public function isActive(): bool
    {
        return $this->status !== 'banni' && $this->banned_at === null;
    }

    public function isBanni(): bool
    {
        return $this->status === 'banni' || $this->banned_at !== null;
    }

    public function bannir(): void
    {
        $this->forceFill([
            'status' => 'banni',
            'banned_at' => now(),
        ])->save();
    }

    public function reactiver(): void
    {
        $this->forceFill([
            'status' => 'actif',
            'banned_at' => null,
        ])->save();
    }
}