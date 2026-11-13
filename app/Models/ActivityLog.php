<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'impersonator_id',
        'method',
        'route_name',
        'url',
        'status',
        'parametres',
        'donnees',
        'ip',
        'duree_ms',
    ];

    protected function casts(): array
    {
        return [
            'parametres' => 'array',
            'donnees' => 'array',
            'created_at' => 'datetime',
        ];
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
