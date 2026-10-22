<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consigne extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'consigne',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Récupérer le nom de l'auteur de la consigne
    public function getAuteurAttribute()
    {
        return $this->user->name ?? 'Direction';
    }
    public function consignes()
{
    return $this->hasMany(Consigne::class)->latest();
}
}