<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteractionClient extends Model
{
    use HasFactory;

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
}