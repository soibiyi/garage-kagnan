<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LigneDevis extends Model
{
    protected $table = 'lignes_devis';

    protected $fillable = [
        'devis_id',
        'quantite',
        'designation',
        'reference_piece',
        'pu_net',
        'remise',
        'montant_ht',
        'montant_ttc',
        'ne_pas_appliquer_tva',
        'type',
        'famille',
        'sous_famille',
        'statut_ligne',
        'is_accepted',
    ];

    public function devis()
    {
        return $this->belongsTo(Devis::class);
    }
}