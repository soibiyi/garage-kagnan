<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\InteractionClient;
use App\Models\User;
use App\Notifications\ClientsAttribues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Attribution manuelle, par l'administrateur, des clients non attribués à un chargé client.
 *
 * Un client « appartient » à un chargé client dès qu'il existe une interaction entre eux :
 * l'attribution crée donc une interaction de type « attribution » (sans véhicule), ce qui
 * ouvre immédiatement le client au chargé choisi sans toucher au reste du contrôle d'accès.
 */
class ClientAttributionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'charge_id' => ['required', Rule::exists('users', 'id')->where('role', 'charge_client')],
            'client_ids' => ['required', 'array', 'min:1', 'max:200'],
            'client_ids.*' => ['integer', 'distinct', Rule::exists('clients', 'id')],
        ], [
            'charge_id.required' => 'Choisissez un chargé client.',
            'charge_id.exists' => 'Choisissez un chargé client valide.',
            'client_ids.required' => 'Sélectionnez au moins un client.',
        ]);

        $charge = User::findOrFail($data['charge_id']);

        // Un client pris en charge entre-temps par un autre chargé n'est pas réattribué.
        $aAttribuer = Client::query()
            ->whereIn('id', $data['client_ids'])
            ->whereDoesntHave('interactions', fn ($i) => $i->whereHas('user', fn ($u) => $u->where('role', 'charge_client')))
            ->get(['id', 'nom', 'prenom']);

        DB::transaction(function () use ($aAttribuer, $charge, $request) {
            foreach ($aAttribuer as $client) {
                InteractionClient::create([
                    'client_id' => $client->id,
                    'user_id' => $charge->id,
                    'type' => InteractionClient::TYPE_ATTRIBUTION,
                    'objet' => 'Client attribué par ' . $request->user()->name,
                ]);
            }
        });

        // Une seule notification par attribution, même groupée
        if ($aAttribuer->isNotEmpty()) {
            $charge->notify(new ClientsAttribues(
                $aAttribuer->map(fn (Client $c) => [
                    'id' => $c->id,
                    'nom' => trim(($c->prenom ?? '') . ' ' . ($c->nom ?? '')) ?: 'Client',
                ])->values()->all(),
                $request->user()->name,
            ));
        }

        $nb = $aAttribuer->count();
        $ignores = count($data['client_ids']) - $nb;

        $message = $nb === 0
            ? 'Aucun client attribué : ils sont déjà suivis par un chargé client.'
            : sprintf('%d client%s attribué%s à %s.', $nb, $nb > 1 ? 's' : '', $nb > 1 ? 's' : '', $charge->name)
                . ($ignores > 0 ? sprintf(' %d déjà suivi%s ignoré%s.', $ignores, $ignores > 1 ? 's' : '', $ignores > 1 ? 's' : '') : '');

        return back()->with($nb === 0 ? 'error' : 'success', $message);
    }
}
