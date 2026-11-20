<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InteractionClient;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleExpiryReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_expired_and_five_day_vehicle_expirations_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createVehicle('ALERT-001', today()->addDays(5)->toDateString(), today()->addDays(6)->toDateString());
        $this->createVehicle('ALERT-002', null, today()->subDay()->toDateString());
        $this->createVehicle('ALERT-003', today()->addDays(6)->toDateString(), null);

        $this->actingAs($admin)
            ->getJson(route('charge_client.vehicules.echeances.notifications'))
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('notifications.0.immatriculation', 'ALERT-002')
            ->assertJsonPath('notifications.1.type', 'expiration_assurance')
            ->assertJsonPath('notifications.1.jours_restants', 5);
    }

    public function test_charge_client_notifications_are_limited_to_assigned_clients(): void
    {
        $chargeClient = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $otherChargeClient = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $assignedClient = $this->createClient('Client attribué');
        $otherClient = $this->createClient('Autre client');
        $this->assign($assignedClient, $chargeClient);
        $this->assign($otherClient, $otherChargeClient);

        $assignedVehicle = $this->createVehicleForClient(
            $assignedClient,
            'ASSIGNED-001',
            today()->addDays(2)->toDateString(),
            null
        );
        $this->createVehicleForClient(
            $otherClient,
            'OTHER-001',
            today()->addDays(2)->toDateString(),
            null
        );

        $this->actingAs($chargeClient)
            ->getJson(route('charge_client.vehicules.echeances.notifications'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.vehicule_id', $assignedVehicle->id);

        $this->actingAs($chargeClient)
            ->get(route('charge_client.vehicules.echeances.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ChargeClient/VehiculeEcheancesIndex')
                ->where('vehicules.total', 1));
    }

    public function test_admin_can_update_a_vehicle_expiration_from_the_tracking_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicule = $this->createVehicle('UPDATE-001', null, null);
        $nouvelleDate = today()->addYear()->toDateString();

        $this->actingAs($admin)
            ->patch(route('charge_client.vehicules.echeances.update', $vehicule), [
                'expiration_assurance' => $nouvelleDate,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicules', [
            'id' => $vehicule->id,
            'expiration_assurance' => $nouvelleDate,
        ]);
    }

    private function createVehicle(
        string $immatriculation,
        ?string $expirationAssurance = null,
        ?string $expirationSicta = null
    ): Vehicule {
        return $this->createVehicleForClient(
            $this->createClient('Client ' . $immatriculation),
            $immatriculation,
            $expirationAssurance,
            $expirationSicta
        );
    }

    private function createVehicleForClient(
        Client $client,
        string $immatriculation,
        ?string $expirationAssurance = null,
        ?string $expirationSicta = null
    ): Vehicule {
        return Vehicule::create([
            'client_id' => $client->id,
            'immatriculation' => $immatriculation,
            'marque' => 'Toyota',
            'modele' => 'Hilux',
            'expiration_assurance' => $expirationAssurance,
            'expiration_sicta' => $expirationSicta,
        ]);
    }

    private function createClient(string $name): Client
    {
        return Client::create([
            'nom' => $name,
            'prenom' => 'Test',
            'telephone' => '770000000',
            'siege' => 'SGK',
        ]);
    }

    private function assign(Client $client, User $chargeClient): void
    {
        InteractionClient::create([
            'client_id' => $client->id,
            'user_id' => $chargeClient->id,
            'type' => 'appel',
        ]);
    }
}
