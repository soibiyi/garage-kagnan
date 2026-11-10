<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EspaceClientAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_must_change_the_unique_temporary_password_before_accessing_their_data(): void
    {
        $client = $this->createClient('Client');
        $vehicle = Vehicule::create([
            'client_id' => $client->id,
            'immatriculation' => 'TEST-001',
            'marque' => 'Toyota',
            'modele' => 'Hilux',
        ]);
        $otherClient = $this->createClient('Autre');
        Vehicule::create([
            'client_id' => $otherClient->id,
            'immatriculation' => 'TEST-002',
            'marque' => 'Honda',
            'modele' => 'Civic',
        ]);

        $temporaryPassword = $client->temporaryPortalPassword();
        $this->assertNotNull($temporaryPassword);
        $this->assertMatchesRegularExpression('/^GK-KEY-[A-Z0-9]{6}-ID-'.$client->id.'$/', $client->portal_identifier);
        $this->assertTrue(Hash::check($temporaryPassword, $client->portal_password));
        $this->assertNotSame($temporaryPassword, $client->portal_password);
        $this->assertNotSame($temporaryPassword, $otherClient->temporaryPortalPassword());
        $this->assertArrayNotHasKey('portal_identifier', $client->toArray());

        $this->postJson('/espace-client/connexion', [
            'identifier' => $client->portal_identifier,
            'password' => $temporaryPassword,
        ])->assertOk()->assertJson(['must_change_password' => true]);

        $this->getJson('/espace-client/api/me')->assertStatus(423);

        $this->patchJson('/espace-client/api/mot-de-passe', [
            'current_password' => $temporaryPassword,
            'password' => 'MonNouveauMotDePasse2026',
            'password_confirmation' => 'MonNouveauMotDePasse2026',
        ])->assertNoContent();

        $response = $this->getJson('/espace-client/api/me')
            ->assertOk()
            ->assertJsonPath('client.id', $client->id)
            ->assertJsonCount(1, 'client.vehicules')
            ->assertJsonPath('client.vehicules.0.id', $vehicle->id);

        $this->assertStringNotContainsString($otherClient->portal_identifier, $response->getContent());
        $this->assertNull($client->fresh()->temporaryPortalPassword());

        $this->postJson('/espace-client/deconnexion')->assertNoContent();
        $this->getJson('/espace-client/api/me')->assertUnauthorized();
    }

    public function test_client_can_activate_without_a_vehicle_and_add_one_later(): void
    {
        $client = $this->createClient('Sans véhicule');
        $temporaryPassword = $client->temporaryPortalPassword();

        $this->postJson('/espace-client/connexion', [
            'identifier' => $client->portal_identifier,
            'password' => $temporaryPassword,
        ])->assertOk()->assertJson(['must_change_password' => true]);

        $this->patchJson('/espace-client/api/mot-de-passe', [
            'current_password' => $temporaryPassword,
            'password' => 'MonNouveauMotDePasse2026',
            'password_confirmation' => 'MonNouveauMotDePasse2026',
        ])->assertNoContent();

        $this->getJson('/espace-client/api/me')->assertOk()->assertJsonCount(0, 'client.vehicules');

        $this->postJson('/espace-client/api/vehicules', [
            'marque' => 'Toyota',
            'modele' => 'Hilux',
            'immatriculation' => 'LATER-001',
            'vin' => 'JT1234567890123456',
            'expiration_assurance' => '2027-06-30',
            'expiration_sicta' => '2027-09-30',
        ])->assertCreated()
            ->assertJsonPath('vehicle.modele', 'Toyota Hilux')
            ->assertJsonPath('vehicle.modele_court', 'Hilux')
            ->assertJsonPath('vehicle.immat', 'LATER-001')
            ->assertJsonPath('vehicle.vin', 'JT1234567890123456')
            ->assertJsonPath('vehicle.expiration_assurance', '2027-06-30')
            ->assertJsonPath('vehicle.expiration_sicta', '2027-09-30');

        $this->getJson('/espace-client/api/me')
            ->assertOk()
            ->assertJsonCount(1, 'client.vehicules')
            ->assertJsonPath('client.vehicules.0.immatriculation', 'LATER-001')
            ->assertJsonPath('client.vehicules.0.vin', 'JT1234567890123456')
            ->assertJsonPath('client.vehicules.0.expiration_assurance', '2027-06-30')
            ->assertJsonPath('client.vehicules.0.expiration_sicta', '2027-09-30');
    }

    public function test_client_can_register_without_a_vehicle_and_receives_a_garage_dossier(): void
    {
        $this->postJson('/espace-client/inscription', [
            'prenom' => 'Nouveau',
            'nom' => 'Client',
            'telephone' => '0700000001',
            'siege' => 'SGK',
            'email' => 'nouveau@example.test',
            'password' => 'MotDePasseSolide2026',
            'password_confirmation' => 'MotDePasseSolide2026',
        ])->assertCreated()
            ->assertJsonStructure(['identifier']);

        $client = Client::where('telephone', '0700000001')->firstOrFail();

        $this->assertMatchesRegularExpression('/^GK-KEY-[A-Z0-9]{6}-ID-'.$client->id.'$/', $client->portal_identifier);
        $this->assertFalse($client->portal_password_change_required);
        $this->assertNull($client->portal_temp_password);
        $this->assertSame('SGK', $client->siege);
        $this->assertSame(0, $client->vehicules()->count());

        $this->getJson('/espace-client/api/me')
            ->assertOk()
            ->assertJsonPath('client.identifier', $client->portal_identifier)
            ->assertJsonPath('client.siege.code', 'SGK')
            ->assertJsonPath('client.siege.nom', 'Siège Garage Kagnan')
            ->assertJsonCount(0, 'client.vehicules');
    }

    public function test_client_can_register_with_an_optional_vehicle(): void
    {
        $this->postJson('/espace-client/inscription', [
            'prenom' => 'Nouveau',
            'nom' => 'Client',
            'telephone' => '0700000002',
            'siege' => 'SGK',
            'password' => 'MotDePasseSolide2026',
            'password_confirmation' => 'MotDePasseSolide2026',
            'vehicle_marque' => 'Toyota',
            'vehicle_modele' => 'Hilux',
            'vehicle_immatriculation' => 'SIGNUP-001',
            'vehicle_vin' => 'JT2234567890123456',
            'vehicle_expiration_assurance' => '2027-06-30',
            'vehicle_expiration_sicta' => '2027-09-30',
        ])->assertCreated();

        $client = Client::where('telephone', '0700000002')->firstOrFail();

        $this->assertDatabaseHas('vehicules', [
            'client_id' => $client->id,
            'immatriculation' => 'SIGNUP-001',
            'marque' => 'Toyota',
            'modele' => 'Hilux',
            'vin' => 'JT2234567890123456',
            'expiration_assurance' => '2027-06-30',
            'expiration_sicta' => '2027-09-30',
        ]);
    }

    public function test_client_cannot_add_a_vehicle_without_its_vin_and_expiration_dates(): void
    {
        $client = $this->createClient('Véhicule incomplet');
        $temporaryPassword = $client->temporaryPortalPassword();

        $this->postJson('/espace-client/connexion', [
            'identifier' => $client->portal_identifier,
            'password' => $temporaryPassword,
        ])->assertOk()->assertJson(['must_change_password' => true]);

        $this->patchJson('/espace-client/api/mot-de-passe', [
            'current_password' => $temporaryPassword,
            'password' => 'MonNouveauMotDePasse2026',
            'password_confirmation' => 'MonNouveauMotDePasse2026',
        ])->assertNoContent();

        $this->postJson('/espace-client/api/vehicules', [
            'modele' => 'Hilux',
            'immatriculation' => 'INCOMPLETE-001',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['vin', 'expiration_assurance', 'expiration_sicta']);

        $this->assertDatabaseCount('vehicules', 0);
    }

    public function test_registration_cannot_create_a_vehicle_without_its_vin_and_expiration_dates(): void
    {
        $this->postJson('/espace-client/inscription', [
            'prenom' => 'Nouveau',
            'nom' => 'Client',
            'telephone' => '0700000020',
            'siege' => 'SGK',
            'password' => 'MotDePasseSolide2026',
            'password_confirmation' => 'MotDePasseSolide2026',
            'add_vehicle' => true,
            'vehicle_modele' => 'Hilux',
            'vehicle_immatriculation' => 'INCOMPLETE-002',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_vin', 'vehicle_expiration_assurance', 'vehicle_expiration_sicta']);

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('vehicules', 0);
    }

    public function test_self_registered_client_is_visible_to_the_garage_client_directory_without_a_vehicle(): void
    {
        $this->postJson('/espace-client/inscription', [
            'prenom' => 'Nouveau',
            'nom' => 'Client',
            'telephone' => '0700000003',
            'siege' => 'SGK',
            'password' => 'MotDePasseSolide2026',
            'password_confirmation' => 'MotDePasseSolide2026',
        ])->assertCreated();

        $client = Client::where('telephone', '0700000003')->firstOrFail();
        $this->assertTrue($client->portal_registered);

        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);

        $this->actingAs($staff)
            ->get('/suivi-client/clients')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ChargeClient/ClientsIndex')
                ->where('clients.0.id', $client->id)
                ->where('clients.0.portal_registered', true));

        $this->get("/suivi-client/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ChargeClient/ClientShow')
                ->where('client.id', $client->id)
                ->where('portalAccess.identifier', $client->portal_identifier));

        $otherStaff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'ZGK',
        ]);

        $this->actingAs($otherStaff)
            ->get('/suivi-client/clients')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('clients', 0));

        $this->get("/suivi-client/clients/{$client->id}")->assertNotFound();
    }

    public function test_registration_requires_a_valid_siege(): void
    {
        $this->postJson('/espace-client/inscription', [
            'prenom' => 'Nouveau',
            'nom' => 'Client',
            'telephone' => '0700000004',
            'password' => 'MotDePasseSolide2026',
            'password_confirmation' => 'MotDePasseSolide2026',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('siege');

        $this->postJson('/espace-client/inscription', [
            'prenom' => 'Nouveau',
            'nom' => 'Client',
            'telephone' => '0700000005',
            'siege' => 'INCONNU',
            'password' => 'MotDePasseSolide2026',
            'password_confirmation' => 'MotDePasseSolide2026',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('siege');

        $this->assertDatabaseMissing('clients', ['telephone' => '0700000004']);
        $this->assertDatabaseMissing('clients', ['telephone' => '0700000005']);
    }

    public function test_default_database_seeder_does_not_create_employee_accounts(): void
    {
        Artisan::call('db:seed');

        $this->assertDatabaseCount('users', 0);
    }

    private function createClient(string $name): Client
    {
        return Client::create([
            'nom' => $name,
            'prenom' => 'Test',
            'telephone' => '0700000000',
        ]);
    }
}
