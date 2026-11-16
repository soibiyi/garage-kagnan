<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intervention extends Model
{
    use \App\Models\Concerns\Audite;

    use HasFactory;

    protected $table = 'interventions';

    protected $guarded = [];

    protected $casts = [
        'date_reception' => 'datetime',
        'photos_supplementaires' => 'array',
    ];

    /**
     * Limite la requête aux dossiers du siège de l'utilisateur connecté.
     * - admin : aucun filtre (voit tous les sièges)
     * - employé avec siège : uniquement les dossiers de son siège
     * - employé sans siège : aucun dossier
     */
    public function scopeDuSiege($query, ?User $user = null)
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role === 'admin') {
            return $query;
        }

        if (empty($user->siege)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->getModel()->getTable() . '.siege', $user->siege);
    }

    /** Ce dossier est-il accessible à cet utilisateur ? (utilisé pour les pages de détail) */
    public function estVisiblePar(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return !empty($user->siege) && $this->siege === $user->siege;
    }

    /**
     * Supprime le dossier avec ses factures, paiements, devis (principal et complémentaires) et lignes,
     * un élément à la fois et des enfants vers le parent. Chaque suppression est ainsi tracée dans le
     * journal d'audit et le super administrateur peut tout restaurer d'un coup. Une suppression laissée
     * à la cascade de la base ne laisserait aucune trace.
     */
    public function supprimerAvecDependances(): void
    {
        Facture::where('intervention_id', $this->id)->get()->each(function (Facture $facture) {
            Paiement::where('facture_id', $facture->id)->get()->each->delete();
            $facture->delete();
        });

        Devis::where('intervention_id', $this->id)->get()->each(function (Devis $devis) {
            LigneDevis::where('devis_id', $devis->id)->get()->each->delete();
            $devis->delete();
        });

        $this->delete();
    }

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function receptionniste()
    {
        return $this->belongsTo(User::class, 'receptionniste_id');
    }

    public function mecanicien()
    {
        return $this->belongsTo(User::class, 'mecanicien_id');
    }

    public function client()
    {
        return $this->hasOneThrough(Client::class, Vehicule::class, 'id', 'id', 'vehicule_id', 'client_id');
    }

    public function devis()
    {
        return $this->hasOne(Devis::class, 'intervention_id')->where('is_complementaire', false);
    }

    public function devisComplements()
    {
        return $this->hasMany(Devis::class, 'intervention_id')
            ->where('is_complementaire', true)
            ->orderBy('id');
    }

    /**
     * Relation avec la facture liée à l'intervention
     */
    public function facture()
    {
        return $this->hasOne(Facture::class, 'intervention_id');
    }
}