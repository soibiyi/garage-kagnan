<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalMessage;
use App\Models\InteractionClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChargeClientDataScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_client_can_only_list_and_open_clients_linked_to_their_interactions(): void
    {
        $charge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $otherCharge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $client = $this->createClient('Client affecté');
        $otherClient = $this->createClient('Client différent');
        $this->assign($client, $charge);
        $this->assign($otherClient, $otherCharge);

        $this->actingAs($charge)
            ->get(route('charge_client.clients.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('clients.0.id', $client->id)
                ->missing('clients.1.id'));

        $this->actingAs($charge)
            ->get(route('charge_client.clients.show', $client))
            ->assertOk();

        $this->actingAs($charge)
            ->get(route('charge_client.clients.show', $otherClient))
            ->assertNotFound();

        $this->actingAs($charge)
            ->get(route('charge_client.clients.chat', $otherClient))
            ->assertNotFound();
    }

    public function test_charge_client_message_alerts_include_only_messages_from_their_clients(): void
    {
        $charge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $otherCharge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $client = $this->createClient('Client affecté');
        $otherClient = $this->createClient('Client différent');
        $this->assign($client, $charge);
        $this->assign($otherClient, $otherCharge);

        foreach ([$client, $otherClient] as $recipient) {
            ClientPortalMessage::create([
                'client_id' => $recipient->id,
                'sender_type' => 'client',
                'message' => "Message de {$recipient->nom}",
            ]);
        }

        $this->actingAs($charge)
            ->getJson(route('charge_client.notifications.messages'))
            ->assertOk()
            ->assertJsonCount(1, 'conversations')
            ->assertJsonPath('conversations.0.client_id', $client->id);
    }

    public function test_charge_client_cannot_open_another_users_vehicle(): void
    {
        $charge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $otherCharge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $client = $this->createClient('Client différent');
        $this->assign($client, $otherCharge);
        $vehicleId = DB::table('vehicules')->insertGetId([
            'client_id' => $client->id,
            'immatriculation' => 'ZX-987-YW',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($charge)
            ->get(route('charge_client.vehicules.show', $vehicleId))
            ->assertNotFound();
    }

    public function test_charge_client_dashboard_only_contains_their_clients_interventions(): void
    {
        $charge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $otherCharge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $client = $this->createClient('Client affecté');
        $otherClient = $this->createClient('Client différent');
        $this->assign($client, $charge);
        $this->assign($otherClient, $otherCharge);

        foreach ([$client, $otherClient] as $index => $owner) {
            $vehicleId = DB::table('vehicules')->insertGetId([
                'client_id' => $owner->id,
                'immatriculation' => 'AA-000-' . $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('interventions')->insert([
                'vehicule_id' => $vehicleId,
                'siege' => 'SGK',
                'kilometrage' => 100,
                'circuit' => 'normal',
                'statut' => 'reception',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($charge)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interventionsAtelier.total', 1)
                ->where('interventionsAtelier.data.0.vehicule.client.id', $client->id));
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

    private function assign(Client $client, User $charge): void
    {
        InteractionClient::create([
            'client_id' => $client->id,
            'user_id' => $charge->id,
            'type' => 'appel',
        ]);
    }
}
