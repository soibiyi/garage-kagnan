<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends Model
{
    protected $fillable = [
        'fingerprint',
        'source',
        'type',
        'message',
        'fichier',
        'ligne',
        'url',
        'method',
        'user_id',
        'trace',
        'occurrences',
        'premiere_fois',
        'derniere_fois',
        'resolue_at',
        'resolue_par',
    ];

    protected function casts(): array
    {
        return [
            'premiere_fois' => 'datetime',
            'derniere_fois' => 'datetime',
            'resolue_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolveur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolue_par');
    }
}
