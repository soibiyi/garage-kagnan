<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DossierPieceAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_piece_search_prefers_inventory_matching_vehicle_make_and_model(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Stock::create([
            'marque' => 'Toyota',
            'modele' => 'Corolla',
            'designation_piece' => 'Filtre à huile',
            'reference' => 'TOY-FIL-01',
            'prix_kagnan_ht' => '12000',
            'famille' => 'Motorisation',
            'sous_famille' => 'Lubrification',
        ]);
        Stock::create([
            'marque' => 'Honda',
            'modele' => 'Civic',
            'designation_piece' => 'Filtre à huile',
            'reference' => 'HON-FIL-01',
            'prix_kagnan_ht' => '9000',
            'famille' => 'Motorisation',
            'sous_famille' => 'Lubrification',
        ]);

        $this->getJson(route('administration.devis.directs.rechercher-pieces', [
            'marque' => 'Toyota',
            'modele' => 'Corolla',
            'q' => 'Filtre',
        ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.designation_piece', 'Filtre à huile')
            ->assertJsonPath('0.reference', 'TOY-FIL-01')
            ->assertJsonPath('0.famille', 'Motorisation')
            ->assertJsonPath('0.sous_famille', 'Lubrification')
            ->assertJsonPath('0.source', 'vehicule');
    }

    public function test_piece_search_suggests_family_from_database_when_vehicle_has_no_matching_inventory(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Stock::create([
            'marque' => 'Toyota',
            'modele' => 'Corolla',
            'designation_piece' => 'Rotule de direction',
            'reference' => 'TOY-ROT-01',
            'prix_kagnan_ht' => '18000',
            'famille' => 'Suspension & Direction',
            'sous_famille' => 'Direction',
        ]);

        $this->getJson(route('administration.devis.directs.rechercher-pieces', [
            'marque' => 'Ford',
            'modele' => 'Focus',
            'q' => 'Rotule',
        ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.designation_piece', null)
            ->assertJsonPath('0.reference', null)
            ->assertJsonPath('0.famille', 'Suspension & Direction')
            ->assertJsonPath('0.sous_famille', 'Direction')
            ->assertJsonPath('0.source', 'famille');
    }
}
