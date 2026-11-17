<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteractionClient extends Model
{
    use HasFactory;

    /** Interaction technique créée quand l'administrateur attribue un client à un chargé client. */
    public const TYPE_ATTRIBUTION = 'attribution';

    protected $fillable = [
        'client_id',
        'user_id',
        'vehicule_id',
        'type',
        'objet',
        'notes',
        'accord_convenu',
        'date_relance_prevue',
    ];

    /** Vrais contacts avec le client (exclut les attributions faites par l'administrateur). */
    public function scopeContacts($query)
    {
        return $query->where('type', '!=', self::TYPE_ATTRIBUTION);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function photos()
    {
        return $this->hasMany(InteractionClientPhoto::class)->orderBy('id');
    }
}