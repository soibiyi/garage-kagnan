<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeClientVehiculeEcheancesTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_client_can_update_vehicle_insurance_and_technical_inspection_expirations(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);
        $vehicle = $this->vehicleWithIntervention('SGK');

        $this->actingAs($staff)
            ->patch(route('charge_client.vehicules.echeances.update', $vehicle), [
                'expiration_assurance' => '2027-03-15',
                'expiration_sicta' => '2027-06-30',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicules', [
            'id' => $vehicle->id,
            'expiration_assurance' => '2027-03-15',
            'expiration_sicta' => '2027-06-30',
        ]);
    }

    public function test_charge_client_can_clear_an_expiration_date(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);
        $vehicle = $this->vehicleWithIntervention('SGK', [
            'expiration_assurance' => '2027-03-15',
            'expiration_sicta' => '2027-06-30',
        ]);

        $this->actingAs($staff)
            ->patch(route('charge_client.vehicules.echeances.update', $vehicle), [
                'expiration_assurance' => '',
                'expiration_sicta' => '2027-06-30',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicules', [
            'id' => $vehicle->id,
            'expiration_assurance' => null,
            'expiration_sicta' => '2027-06-30',
        ]);
    }

    public function test_charge_client_cannot_update_vehicle_outside_their_siege(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);
        $vehicle = $this->vehicleWithIntervention('ZGK');

        $this->actingAs($staff)
            ->patch(route('charge_client.vehicules.echeances.update', $vehicle), [
                'expiration_assurance' => '2027-03-15',
                'expiration_sicta' => '2027-06-30',
            ])
            ->assertNotFound();
    }

    public function test_other_roles_cannot_update_vehicle_expirations(): void
    {
        $staff = User::factory()->create([
            'role' => 'receptionniste',
            'siege' => 'SGK',
        ]);
        $vehicle = $this->vehicleWithIntervention('SGK');

        $this->actingAs($staff)
            ->patch(route('charge_client.vehicules.echeances.update', $vehicle), [
                'expiration_assurance' => '2027-03-15',
                'expiration_sicta' => '2027-06-30',
            ])
            ->assertForbidden();
    }

    public function test_expiration_fields_must_use_valid_date_values(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);
        $vehicle = $this->vehicleWithIntervention('SGK');

        $this->actingAs($staff)
            ->from(route('charge_client.vehicules.show', $vehicle))
            ->patch(route('charge_client.vehicules.echeances.update', $vehicle), [
                'expiration_assurance' => '15/03/2027',
                'expiration_sicta' => '2027-06-30',
            ])
            ->assertSessionHasErrors('expiration_assurance');
    }

    public function test_charge_client_can_see_online_clients_vehicles_before_the_first_intervention(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);
        $client = Client::create([
            'nom' => 'Kouassi',
            'prenom' => 'Aminata',
            'telephone' => '0700000030',
            'siege' => 'SGK',
        ]);
        $client->forceFill(['portal_registered' => true])->save();
        $vehicle = $client->vehicules()->create([
            'immatriculation' => 'PORTAL-001',
            'marque' => 'Toyota',
            'modele' => 'Hilux',
        ]);

        $this->actingAs($staff)
            ->get(route('charge_client.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ChargeClient/ClientsIndex')
                // Pas encore suivi par un chargé client : listé parmi les clients non attribués
                ->has('clients', 0)
                ->has('orphelins', 1)
                ->where('orphelins.0.id', $client->id)
                ->where('orphelins.0.vehicules.0.id', $vehicle->id)
                ->where('orphelins.0.vehicules.0.immatriculation', 'PORTAL-001')
            );

        $this->actingAs($staff)
            ->get(route('charge_client.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ChargeClient/ClientShow')
                ->where('client.vehicules.0.id', $vehicle->id)
            );

        $this->actingAs($staff)
            ->get(route('charge_client.vehicules.show', $vehicle))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ChargeClient/VehiculeShow')
                ->where('vehicule.id', $vehicle->id)
            );
    }

    public function test_charge_client_cannot_see_online_client_vehicles_from_another_siege(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);
        $client = Client::create([
            'nom' => 'Traore',
            'prenom' => 'Moussa',
            'telephone' => '0700000031',
            'siege' => 'ZGK',
        ]);
        $vehicle = $client->vehicules()->create([
            'immatriculation' => 'OTHER-SIEGE-001',
            'marque' => 'Honda',
            'modele' => 'Civic',
        ]);

        $this->actingAs($staff)
            ->get(route('charge_client.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('clients', 0));

        $this->actingAs($staff)
            ->get(route('charge_client.vehicules.show', $vehicle))
            ->assertNotFound();
    }

    private function vehicleWithIntervention(string $siege, array $dates = [])
    {
        $client = Client::create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'telephone' => '0700000000',
            'siege' => $siege,
        ]);
        $vehicle = $client->vehicules()->create(array_merge([
            'immatriculation' => 'TEST-'.uniqid(),
            'marque' => 'Toyota',
            'modele' => 'Corolla',
        ], $dates));
        $vehicle->interventions()->create([
            'kilometrage' => 100,
            'siege' => $siege,
        ]);

        return $vehicle;
    }
}
