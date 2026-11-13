<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consigne;
use App\Models\InteractionClient;
use App\Models\User;
use App\Notifications\AdminInstructionReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminInstructionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_instruction_notifies_only_charge_clients_for_that_client_and_siege(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedCharge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $unrelatedCharge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        $otherSiegeCharge = User::factory()->create(['role' => 'charge_client', 'siege' => 'ZGK']);
        $client = Client::create([
            'nom' => 'Diallo',
            'prenom' => 'Awa',
            'telephone' => '770000000',
            'siege' => 'SGK',
        ]);

        InteractionClient::create([
            'client_id' => $client->id,
            'user_id' => $assignedCharge->id,
            'type' => 'appel',
        ]);
        InteractionClient::create([
            'client_id' => $client->id,
            'user_id' => $otherSiegeCharge->id,
            'type' => 'appel',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.consignes.store', $client), [
                'consigne' => 'Rappeler le client demain.',
            ])
            ->assertRedirect();

        $consigne = Consigne::firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $assignedCharge->id,
            'type' => AdminInstructionReceived::class,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $unrelatedCharge->id,
            'type' => AdminInstructionReceived::class,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $otherSiegeCharge->id,
            'type' => AdminInstructionReceived::class,
        ]);

        $response = $this->actingAs($assignedCharge)
            ->getJson('/suivi-client/notifications/instructions')
            ->assertOk()
            ->assertJsonPath('notifications.0.data.message', "Vous venez de recevoir une instruction de la part de l'administrateur.")
            ->assertJsonPath('notifications.0.data.consigne_id', $consigne->id)
            ->assertJsonPath('notifications.0.data.client_id', $client->id);

        $notificationId = $response->json('notifications.0.id');
        $this->actingAs($assignedCharge)
            ->patchJson("/suivi-client/notifications/instructions/{$notificationId}/read")
            ->assertNoContent();

        $this->actingAs($assignedCharge)
            ->getJson('/suivi-client/notifications/instructions')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('notifications.0.id', $notificationId)
            ->assertJsonPath('notifications.0.read_at', fn ($value) => $value !== null);

        $this->actingAs($assignedCharge)
            ->get(route('charge_client.clients.chat', $client) . '?instruction=' . $consigne->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('adminInstruction.consigne', 'Rappeler le client demain.')
                ->where('adminInstruction.auteur', $admin->name));

        $vehiculeId = DB::table('vehicules')->insertGetId([
            'client_id' => $client->id,
            'immatriculation' => 'AB-123-CD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($assignedCharge)
            ->get(route('charge_client.vehicules.relances', $vehiculeId))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('vehicule.client.consignes.0.consigne', 'Rappeler le client demain.'));

        $assignedCharge->notifications()
            ->whereKey($notificationId)
            ->update(['read_at' => now()->subHours(72)->subSecond()]);

        $this->actingAs($assignedCharge)
            ->getJson('/suivi-client/notifications/instructions')
            ->assertOk()
            ->assertJsonCount(0, 'notifications');

        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
    }
}
