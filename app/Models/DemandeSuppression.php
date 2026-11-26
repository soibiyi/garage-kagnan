<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Demande de suppression d'un dossier ou d'un devis direct, en attente de la décision d'un administrateur. */
class DemandeSuppression extends Model
{
    protected $table = 'demandes_suppression';

    public const TYPES = [
        'dossier' => 'Dossier',
        'devis_direct' => 'Devis direct',
        'facturation' => 'Facturation',
    ];

    protected $fillable = [
        'type', 'cible_id', 'libelle', 'contexte', 'motif', 'siege', 'demandeur_id',
        'statut', 'traite_par', 'traite_le', 'reponse',
    ];

    protected function casts(): array
    {
        return [
            'contexte' => 'json:unicode',
            'traite_le' => 'datetime',
        ];
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
