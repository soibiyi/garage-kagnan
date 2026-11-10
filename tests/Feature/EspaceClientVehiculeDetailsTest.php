<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Intervention;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspaceClientVehiculeDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_update_vehicle_expirations_and_current_mileage(): void
    {
        [$client, $vehicule] = $this->createClientWithVehicle();

        $this->actingAs($client, 'client')
            ->patchJson("/espace-client/api/vehicules/{$vehicule->id}", [
                'expiration_assurance' => '2028-04-15',
                'expiration_sicta' => '2028-09-20',
                'kilometrage' => 125000,
            ])
            ->assertOk()
            ->assertJsonPath('vehicle.expiration_assurance', '2028-04-15')
            ->assertJsonPath('vehicle.expiration_sicta', '2028-09-20')
            ->assertJsonPath('vehicle.kilometrage', 125000);

        $this->assertDatabaseHas('vehicules', [
            'id' => $vehicule->id,
            'expiration_assurance' => '2028-04-15',
            'expiration_sicta' => '2028-09-20',
            'kilometrage' => 125000,
        ]);
    }

    public function test_client_can_record_and_view_an_external_repair_for_their_vehicle(): void
    {
        [$client, $vehicule] = $this->createClientWithVehicle();

        $this->actingAs($client, 'client')
            ->postJson("/espace-client/api/vehicules/{$vehicule->id}/reparations", [
                'date_reparation' => '2026-10-12',
                'lieu' => 'Garage du Plateau',
                'description' => 'Remplacement des plaquettes de frein',
                'kilometrage' => 117500,
                'montant' => 45000,
            ])
            ->assertCreated()
            ->assertJsonPath('repair.lieu', 'Garage du Plateau')
            ->assertJsonPath('repair.kilometrage', 117500);

        $this->getJson('/espace-client/api/me')
            ->assertOk()
            ->assertJsonPath('client.vehicules.0.reparations_externes.0.date_reparation', '2026-10-12')
            ->assertJsonPath('client.vehicules.0.reparations_externes.0.description', 'Remplacement des plaquettes de frein')
            ->assertJsonPath('client.vehicules.0.reparations_externes.0.montant', '45000.00');

        $this->assertDatabaseHas('vehicule_reparations_externes', [
            'vehicule_id' => $vehicule->id,
            'lieu' => 'Garage du Plateau',
            'description' => 'Remplacement des plaquettes de frein',
            'kilometrage' => 117500,
        ]);
    }

    public function test_client_cannot_change_or_add_repairs_to_another_clients_vehicle(): void
    {
        [$client] = $this->createClientWithVehicle();
        [, $otherVehicule] = $this->createClientWithVehicle('OTHER-VEHICLE');

        $this->actingAs($client, 'client')
            ->patchJson("/espace-client/api/vehicules/{$otherVehicule->id}", [
                'expiration_assurance' => '2028-04-15',
                'expiration_sicta' => '2028-09-20',
                'kilometrage' => 125000,
            ])
            ->assertNotFound();

        $this->postJson("/espace-client/api/vehicules/{$otherVehicule->id}/reparations", [
            'date_reparation' => '2026-10-12',
            'lieu' => 'Garage du Plateau',
            'description' => 'Vidange',
        ])->assertNotFound();

        $this->assertDatabaseCount('vehicule_reparations_externes', 0);
    }

    public function test_external_repair_requires_a_valid_past_date_and_description(): void
    {
        [$client, $vehicule] = $this->createClientWithVehicle();

        $this->actingAs($client, 'client')
            ->postJson("/espace-client/api/vehicules/{$vehicule->id}/reparations", [
                'date_reparation' => now()->addDay()->toDateString(),
                'lieu' => 'Garage du Plateau',
                'description' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_reparation', 'description']);
    }

    public function test_client_api_includes_reception_details_for_the_intervention_tracking_modal(): void
    {
        [$client, $vehicule] = $this->createClientWithVehicle();
        Intervention::create([
            'vehicule_id' => $vehicule->id,
            'numero_ot' => 'OT-2026-001',
            'date_reception' => '2026-11-08',
            'kilometrage' => 125000,
            'personne_a_contacter' => '0700000000',
            'circuit' => 'normal',
            'essai_effectue' => true,
            'rapport_mecanicien' => 'Vérification du système de freinage.',
            'allume_cigare' => true,
            'roue_secours' => true,
            'pare_brise_fissure' => true,
            'niveau_carburant' => 'Moitié',
            'intervalle_niveau_carburant' => 'Entre 1/4 et 1/2',
            'remarques_eventuelles' => 'Rayure côté gauche.',
            'photo_avant' => 'interventions/photos/avant.jpg',
            'photos_supplementaires' => ['interventions/photos/detail.jpg'],
            'statut' => 'atelier',
            'siege' => 'SGK',
        ]);

        $this->actingAs($client, 'client')
            ->getJson('/espace-client/api/me')
            ->assertOk()
            ->assertJsonPath('client.vehicules.0.interventions.0.numero_ot', 'OT-2026-001')
            ->assertJsonPath('client.vehicules.0.interventions.0.reception.personne_a_contacter', '0700000000')
            ->assertJsonPath('client.vehicules.0.interventions.0.reception.essai_effectue', true)
            ->assertJsonPath('client.vehicules.0.interventions.0.reception.equipements.roue_secours', true)
            ->assertJsonPath('client.vehicules.0.interventions.0.reception.remarques', 'Rayure côté gauche.')
            ->assertJsonPath('client.vehicules.0.interventions.0.reception.photos.0.label', 'Avant')
            ->assertJsonPath('client.vehicules.0.interventions.0.reception.photos.1.label', 'Supplémentaire 1')
            ->assertJsonPath('client.vehicules.0.interventions.0.rapport', 'Vérification du système de freinage.');
    }

    public function test_delivered_intervention_is_reported_as_tested_in_the_client_portal(): void
    {
        [$client, $vehicule] = $this->createClientWithVehicle();
        Intervention::create([
            'vehicule_id' => $vehicule->id,
            'kilometrage' => 125000,
            'statut' => 'livre',
            'essai_effectue' => false,
            'siege' => 'SGK',
        ]);

        $this->actingAs($client, 'client')
            ->getJson('/espace-client/api/me')
            ->assertOk()
            ->assertJsonPath('client.vehicules.0.interventions.0.statut', 'livre')
            ->assertJsonPath('client.vehicules.0.interventions.0.reception.essai_effectue', true);
    }

    private function createClientWithVehicle(string $immatriculation = 'CLIENT-VEHICLE-001'): array
    {
        $client = Client::create([
            'nom' => 'Client',
            'prenom' => 'Portail',
            'telephone' => '0700000000',
        ]);
        $client->forceFill(['portal_password_change_required' => false])->save();

        $vehicule = $client->vehicules()->create([
            'immatriculation' => $immatriculation,
            'marque' => 'Toyota',
            'modele' => 'Hilux',
            'vin' => strtoupper("VIN-{$immatriculation}"),
            'expiration_assurance' => '2027-06-30',
            'expiration_sicta' => '2027-09-30',
        ]);

        return [$client, $vehicule];
    }
}
