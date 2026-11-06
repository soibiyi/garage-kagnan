<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
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
