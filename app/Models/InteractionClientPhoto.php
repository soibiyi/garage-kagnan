<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InteractionClientPhoto extends Model
{
    protected $fillable = [
        'interaction_client_id',
        'path',
        'nom',
        'mime',
        'taille',
    ];

    protected $hidden = ['path'];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        // Le fichier disparaît avec la photo
        static::deleted(fn (self $photo) => Storage::disk('local')->delete($photo->path));
    }

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(InteractionClient::class, 'interaction_client_id');
    }

    public function getUrlAttribute(): string
    {
        return route('charge_client.interactions.photos.show', $this);
    }
}
