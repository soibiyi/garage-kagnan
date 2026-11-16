<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Paramètre global (clé → valeur JSON). Lire via App\Support\Parametres. */
class Parametre extends Model
{
    protected $primaryKey = 'cle';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['cle', 'valeur', 'modifie_par'];

    protected function casts(): array
    {
        return ['valeur' => 'json'];
    }

    public function modifiePar()
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }
}
