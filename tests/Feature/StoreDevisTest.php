<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Intervention;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreDevisTest extends TestCase
{
    use RefreshDatabase;

    public function test_devis_lines_save_optional_family_fields(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $client = Client::create([
            'nom' => 'Client',
            'prenom' => 'Test',
            'telephone' => '0700000000',
        ]);
        $vehicle = Vehicule::create([
            'client_id' => $client->id,
            'immatriculation' => 'DEVIS-001',
            'marque' => 'Toyota',
            'modele' => 'Hilux',
        ]);
        $intervention = Intervention::create([
            'vehicule_id' => $vehicle->id,
            'kilometrage' => 10000,
            'siege' => 'SGK',
        ]);

        $this->actingAs($staff)
            ->post("/administration/dossiers/{$intervention->id}/devis", [
                'lignes' => [
                    [
                        'quantite' => 1,
                        'designation' => 'PNEU',
                        'pu_net' => 10000,
                        'remise' => 0,
                        'ne_pas_appliquer_tva' => true,
                        'famille' => 'Main d’œuvre & prestations',
                    ],
                    [
                        'quantite' => 2,
                        'designation' => 'Filtre',
                        'pu_net' => 5000,
                        'remise' => 0,
                        'famille' => 'Pièces',
                        'sous_famille' => 'Filtration',
                    ],
                ],
            ])
            ->assertRedirect(route('administration.dossiers.index'));

        $this->assertDatabaseHas('lignes_devis', [
            'designation' => 'PNEU',
            'famille' => 'Main d’œuvre & prestations',
            'sous_famille' => null,
        ]);
        $this->assertDatabaseHas('lignes_devis', [
            'designation' => 'Filtre',
            'famille' => 'Pièces',
            'sous_famille' => 'Filtration',
        ]);
    }
}
