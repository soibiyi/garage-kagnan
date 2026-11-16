<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\Intervention;
use App\Models\LigneDevis;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreDevisTest extends TestCase
{
    use RefreshDatabase;

    public function test_complementary_quote_is_created_without_replacing_the_initial_quote_or_changing_intervention_status(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $client = Client::create([
            'nom' => 'Client',
            'prenom' => 'Test',
            'telephone' => '0700000000',
        ]);
        $vehicle = Vehicule::create([
            'client_id' => $client->id,
            'immatriculation' => 'DEVIS-002',
            'marque' => 'Toyota',
            'modele' => 'Hilux',
        ]);
        $intervention = Intervention::create([
            'vehicule_id' => $vehicle->id,
            'kilometrage' => 10000,
            'siege' => 'SGK',
            'statut' => 'accepte',
        ]);
        $initial = Devis::create([
            'intervention_id' => $intervention->id,
            'statut' => 'accepte',
        ]);

        $this->actingAs($staff)
            ->get(route('administration.devis.complementaire.create', $intervention->id))
            ->assertInertia(fn ($page) => $page
                ->where('complementaire', true)
                ->where('dossier.id', $intervention->id));

        $this->actingAs($staff)
            ->post(route('administration.devis.complementaire.store', $intervention->id), [
                'lignes' => [[
                    'quantite' => 1,
                    'designation' => 'Réparation complémentaire',
                    'pu_net' => 20000,
                    'remise' => 0,
                ]],
            ])
            ->assertRedirect(route('administration.facturation.show', $intervention->id));

        $this->assertDatabaseHas('devis', [
            'intervention_id' => $intervention->id,
            'is_complementaire' => true,
            'statut' => 'en_attente',
        ]);
        $this->assertSame($initial->id, $intervention->fresh()->devis->id);
        $this->assertSame('accepte', $intervention->fresh()->statut);
        $this->assertSame(1, $intervention->devisComplements()->count());
    }

    public function test_facture_total_includes_accepted_lines_from_all_quotes_on_the_intervention(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $client = Client::create([
            'nom' => 'Client',
            'prenom' => 'Test',
            'telephone' => '0700000000',
        ]);
        $vehicle = Vehicule::create([
            'client_id' => $client->id,
            'immatriculation' => 'DEVIS-003',
            'marque' => 'Toyota',
            'modele' => 'Hilux',
        ]);
        $intervention = Intervention::create([
            'vehicule_id' => $vehicle->id,
            'kilometrage' => 10000,
            'siege' => 'SGK',
            'statut' => 'accepte',
        ]);

        foreach ([
            ['is_complementaire' => false, 'montant_ttc' => 11800],
            ['is_complementaire' => true, 'montant_ttc' => 23600],
        ] as $quoteData) {
            $devis = Devis::create([
                'intervention_id' => $intervention->id,
                'statut' => 'accepte',
                'is_complementaire' => $quoteData['is_complementaire'],
            ]);
            LigneDevis::create([
                'devis_id' => $devis->id,
                'designation' => 'Prestation',
                'quantite' => 1,
                'pu_net' => $quoteData['montant_ttc'],
                'montant_ht' => $quoteData['montant_ttc'],
                'montant_ttc' => $quoteData['montant_ttc'],
                'is_accepted' => true,
            ]);
        }

        $this->actingAs($staff)
            ->get(route('administration.factures.index'))
            ->assertInertia(fn ($page) => $page
                ->where('dossiers.data.0.resume_paiement.total_ttc', 36462)
                ->where('dossiers.data.0.resume_paiement.petite_fourniture', 1062));
    }

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
