<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Intervention;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevisDirectClientAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_client_name_creates_distinct_clients_and_links_each_new_vehicle_correctly(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);

        foreach ([
            ['prenom' => 'Said', 'immatriculation' => 'SGK-001-AA'],
            ['prenom' => 'Awa', 'immatriculation' => 'SGK-002-AA'],
        ] as $personne) {
            $this->actingAs($staff)
                ->post(route('administration.devis.directs.store'), $this->devisData([
                    'nouveau_client_nom' => 'Mme',
                    'nouveau_client_prenom' => $personne['prenom'],
                    'nouvelle_immatriculation' => $personne['immatriculation'],
                ]))
                ->assertRedirect(route('dashboard'));
        }

        $clients = Client::where('nom', 'Mme')->orderBy('prenom')->get();

        $this->assertCount(2, $clients);
        $this->assertSame(
            $clients[0]->id,
            Vehicule::where('immatriculation', 'SGK-002-AA')->value('client_id')
        );
        $this->assertSame(
            $clients[1]->id,
            Vehicule::where('immatriculation', 'SGK-001-AA')->value('client_id')
        );
    }

    public function test_existing_client_can_be_selected_to_add_a_vehicle_without_matching_by_name(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);
        $selectedClient = Client::create([
            'nom' => 'Mme',
            'prenom' => 'Said',
            'telephone' => '0700000000',
            'siege' => 'SGK',
        ]);
        Client::create([
            'nom' => 'Mme',
            'prenom' => 'Awa',
            'telephone' => '0711111111',
            'siege' => 'SGK',
        ]);

        $this->actingAs($staff)
            ->post(route('administration.devis.directs.store'), $this->devisData([
                'client_id' => $selectedClient->id,
                'nouvelle_marque' => 'Toyota',
                'nouveau_modele' => 'Corolla',
                'nouvelle_immatriculation' => 'SGK-003-AA',
            ]))
            ->assertRedirect(route('dashboard'));

        $vehicule = Vehicule::where('immatriculation', 'SGK-003-AA')->firstOrFail();

        $this->assertSame($selectedClient->id, $vehicule->client_id);
        $this->assertDatabaseHas('interventions', [
            'vehicule_id' => $vehicule->id,
            'circuit' => 'devis_direct',
        ]);
    }

    public function test_new_vehicle_cannot_silently_reuse_an_existing_registration(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);
        $owner = Client::create([
            'nom' => 'Traore',
            'prenom' => 'Moussa',
            'telephone' => '0700000000',
            'siege' => 'SGK',
        ]);
        Vehicule::create([
            'client_id' => $owner->id,
            'immatriculation' => 'SGK-004-AA',
            'marque' => 'Honda',
            'modele' => 'Civic',
        ]);

        $this->actingAs($staff)
            ->from(route('administration.devis.directs.create'))
            ->post(route('administration.devis.directs.store'), $this->devisData([
                'nouveau_client_nom' => 'Diallo',
                'nouveau_client_prenom' => 'Aminata',
                'nouvelle_immatriculation' => 'SGK 004 AA',
            ]))
            ->assertRedirect(route('administration.devis.directs.create'))
            ->assertSessionHasErrors('nouvelle_immatriculation');

        $this->assertSame(1, Client::count());
        $this->assertSame(0, Intervention::count());
    }

    private function devisData(array $overrides = []): array
    {
        return array_merge([
            'nouveau_client_nom' => 'Diallo',
            'nouveau_client_prenom' => 'Aminata',
            'nouvelle_marque' => 'Toyota',
            'nouveau_modele' => 'Corolla',
            'kilometrage' => 10000,
            'lignes' => [
                [
                    'quantite' => 1,
                    'designation' => 'Diagnostic',
                    'pu_net' => 10000,
                ],
            ],
        ], $overrides);
    }
}
