<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspaceClientPortalRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_requests_are_saved_and_visible_to_the_client_and_garage(): void
    {
        $client = $this->registerClient('0700000201');
        $vehicle = $client->vehicules()->create([
            'immatriculation' => 'REQ-CLIENT-001',
            'marque' => 'Toyota',
            'modele' => 'Corolla',
        ]);

        $this->postJson('/espace-client/api/demandes', [
            'type' => 'rendez_vous',
            'vehicule_id' => $vehicle->id,
            'prestation' => 'Révision / vidange',
            'date_souhaitee' => now()->addDays(3)->toDateString(),
            'precision' => 'Disponible le matin.',
        ])->assertCreated()
            ->assertJsonPath('request.statut', 'en_attente')
            ->assertJsonPath('message', 'Votre demande a été transmise au Garage Kagnan. Elle reste en attente de confirmation.');

        $this->assertDatabaseHas('client_portal_requests', [
            'client_id' => $client->id,
            'vehicule_id' => $vehicle->id,
            'siege' => 'SGK',
            'type' => 'rendez_vous',
            'statut' => 'en_attente',
        ]);

        $this->getJson('/espace-client/api/me')
            ->assertOk()
            ->assertJsonPath('client.demandes.0.type', 'rendez_vous')
            ->assertJsonPath('client.demandes.0.statut', 'en_attente')
            ->assertJsonPath('client.demandes.0.vehicule.immatriculation', 'REQ-CLIENT-001')
            ->assertJsonPath('client.demandes.0.details.prestation', 'Révision / vidange');

        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);

        $this->actingAs($staff)
            ->get("/suivi-client/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('client.portal_requests.0.type', 'rendez_vous')
                ->where('client.portal_requests.0.statut', 'en_attente'));
    }

    public function test_client_cannot_create_a_request_for_another_clients_vehicle(): void
    {
        $client = $this->registerClient('0700000202');
        $other = Client::create([
            'nom' => 'Autre',
            'prenom' => 'Client',
            'telephone' => '0700000203',
        ]);
        $vehicle = $other->vehicules()->create([
            'immatriculation' => 'REQ-CLIENT-002',
            'marque' => 'Honda',
            'modele' => 'Civic',
        ]);

        $this->postJson('/espace-client/api/demandes', [
            'type' => 'message',
            'vehicule_id' => $vehicle->id,
            'message' => 'Bonjour',
        ])->assertNotFound();

        $this->assertDatabaseCount('client_portal_requests', 0);
    }

    public function test_client_and_staff_can_exchange_private_portal_messages(): void
    {
        $client = $this->registerClient('0700000204');
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);

        $this->postJson('/espace-client/api/messages', [
            'message' => 'Bonjour, où en est mon véhicule ?',
        ])->assertCreated()
            ->assertJsonPath('message.sender_type', 'client');

        $this->actingAs($staff)
            ->postJson("/suivi-client/clients/{$client->id}/portail/messages", [
                'message' => 'Votre véhicule est en cours de diagnostic.',
            ])->assertCreated()
                ->assertJsonPath('message.sender_type', 'staff');

        auth()->logout();

        $this->getJson('/espace-client/api/messages')
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.message', 'Bonjour, où en est mon véhicule ?')
            ->assertJsonPath('messages.1.message', 'Votre véhicule est en cours de diagnostic.');

        $this->assertDatabaseCount('client_portal_messages', 2);
    }

    public function test_client_cannot_read_another_clients_messages(): void
    {
        $firstClient = $this->registerClient('0700000205');
        $this->postJson('/espace-client/api/messages', ['message' => 'Message privé'])->assertCreated();
        $this->post('/espace-client/deconnexion');

        $this->registerClient('0700000206');

        $this->getJson('/espace-client/api/messages')
            ->assertOk()
            ->assertJsonCount(0, 'messages');

        $this->assertDatabaseHas('client_portal_messages', [
            'client_id' => $firstClient->id,
            'message' => 'Message privé',
        ]);
    }

    public function test_charge_client_can_accept_a_pending_appointment(): void
    {
        $client = $this->registerClient('0700000207');
        $appointment = $client->portalRequests()->create([
            'siege' => 'SGK',
            'type' => 'rendez_vous',
            'details' => [
                'prestation' => 'Révision / vidange',
                'date_souhaitee' => now()->addDays(3)->toDateString(),
            ],
            'statut' => 'en_attente',
        ]);
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);

        $this->actingAs($staff)
            ->post("/suivi-client/clients/{$client->id}/portail/demandes/{$appointment->id}/accepter")
            ->assertRedirect();

        $this->assertDatabaseHas('client_portal_requests', [
            'id' => $appointment->id,
            'statut' => 'accepte',
            'traite_par' => $staff->id,
        ]);

        $this->getJson('/espace-client/api/me')
            ->assertOk()
            ->assertJsonPath('client.demandes.0.statut', 'accepte');

        $this->actingAs($staff)
            ->post("/suivi-client/clients/{$client->id}/portail/demandes/{$appointment->id}/accepter")
            ->assertStatus(409);
    }

    public function test_charge_client_cannot_accept_an_appointment_from_another_siege(): void
    {
        $client = $this->registerClient('0700000208');
        $appointment = $client->portalRequests()->create([
            'siege' => 'ZGK',
            'type' => 'rendez_vous',
            'details' => ['date_souhaitee' => now()->addDays(3)->toDateString()],
            'statut' => 'en_attente',
        ]);
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);

        $this->actingAs($staff)
            ->post("/suivi-client/clients/{$client->id}/portail/demandes/{$appointment->id}/accepter")
            ->assertNotFound();

        $this->assertDatabaseHas('client_portal_requests', [
            'id' => $appointment->id,
            'statut' => 'en_attente',
        ]);
    }

    public function test_charge_client_can_access_chat_for_a_client_created_offline(): void
    {
        $client = Client::create([
            'nom' => 'Hors ligne',
            'prenom' => 'Client',
            'telephone' => '0700000210',
            'siege' => 'SGK',
        ]);
        $this->assertFalse((bool) $client->portal_registered);

        $message = ClientPortalMessage::create([
            'client_id' => $client->id,
            'sender_type' => 'client',
            'message' => 'Bonjour, je souhaite un suivi.',
        ]);
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);

        $this->actingAs($staff)
            ->get('/suivi-client/clients')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('clients', 1));

        $this->getJson("/suivi-client/clients/{$client->id}/portail/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $message->id)
            ->assertJsonPath('messages.0.message', 'Bonjour, je souhaite un suivi.');
    }

    public function test_dispatch_requests_require_location_and_explicit_gps_consent(): void
    {
        $this->registerClient('0700000211');

        $this->postJson('/espace-client/api/demandes', [
            'type' => 'depannage',
            'lieu' => 'Plateau, Abidjan',
            'categorie_tarif' => 'voiture_particuliere',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['client_lat', 'client_lng', 'gps_consent']);
    }

    public function test_abidjan_passenger_car_towing_uses_published_day_tariff(): void
    {
        $client = $this->registerClient('0700000212');
        $this->travelTo(now()->setTimezone('Africa/Abidjan')->setTime(12, 0));

        $this->postJson('/espace-client/api/demandes', [
            'type' => 'depannage',
            'lieu' => 'Plateau, Abidjan',
            'categorie_tarif' => 'voiture_particuliere',
            'client_lat' => 5.32,
            'client_lng' => -4.01,
            'gps_consent' => true,
        ])->assertCreated()
            ->assertJsonPath('request.prix_estime', 35000);

        $this->assertDatabaseHas('client_portal_requests', [
            'client_id' => $client->id,
            'siege' => 'SGK',
            'prix_estime' => 35000,
            'client_lat' => 5.32,
        ]);
    }

    private function registerClient(string $telephone): Client
    {
        $this->postJson('/espace-client/inscription', [
            'prenom' => 'Client',
            'nom' => 'Test',
            'telephone' => $telephone,
            'siege' => 'SGK',
            'password' => 'MotDePasseSolide2026',
            'password_confirmation' => 'MotDePasseSolide2026',
        ])->assertCreated();

        return Client::where('telephone', $telephone)->firstOrFail();
    }
}
