<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChauffeurDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_chauffeur_for_the_active_siege(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'siege' => null]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Chauffeur Test',
            'email' => 'chauffeur@example.test',
            'password' => 'password123',
            'role' => 'chauffeur',
        ])->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'email' => 'chauffeur@example.test',
            'role' => 'chauffeur',
            'siege' => 'SGK',
        ]);
    }

    public function test_chauffeur_sees_only_pending_dispatch_requests_for_their_siege(): void
    {
        $driver = $this->chauffeur('SGK');
        $available = $this->request('SGK');
        $otherSeat = $this->request('ZGK');
        $appointment = $this->request('SGK', 'rendez_vous');

        $this->actingAs($driver)
            ->get('/chauffeur/missions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('missionsDisponibles.0.id', $available->id)
                ->missing('missionsDisponibles.1.id'));

        $this->assertNotSame($available->id, $otherSeat->id);
        $this->assertNotSame($available->id, $appointment->id);
    }

    public function test_chauffeur_sees_only_their_own_completed_missions_in_history(): void
    {
        $driver = $this->chauffeur('SGK');
        $otherDriver = $this->chauffeur('SGK');
        $completed = $this->request('SGK');
        $completed->update([
            'chauffeur_id' => $driver->id,
            'statut' => 'terminee',
            'mission_completed_at' => now(),
        ]);
        $otherCompleted = $this->request('SGK');
        $otherCompleted->update([
            'chauffeur_id' => $otherDriver->id,
            'statut' => 'terminee',
            'mission_completed_at' => now(),
        ]);
        $stillActive = $this->request('SGK');
        $stillActive->update([
            'chauffeur_id' => $driver->id,
            'statut' => 'en_cours',
        ]);

        $this->actingAs($driver)
            ->get('/chauffeur/missions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('historiqueMissions.data.0.id', $completed->id)
                ->where('historiqueMissions.total', 1));
    }

    public function test_admin_can_view_completed_mission_history_with_responsible_driver_and_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = $this->chauffeur('ZGK');
        $mission = $this->request('ZGK', 'convoyage');
        $mission->update([
            'chauffeur_id' => $driver->id,
            'chauffeur_accepted_at' => now()->subHour(),
            'mission_started_at' => now()->subMinutes(30),
            'mission_completed_at' => now(),
            'statut' => 'terminee',
            'prix_estime' => 42000,
            'tarif_reference' => 'Tarif validé',
        ]);
        $this->request('ZGK')->update([
            'chauffeur_id' => $driver->id,
            'statut' => 'en_cours',
        ]);

        $this->actingAs($admin)
            ->get('/admin/missions/historique')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('missions.data.0.id', $mission->id)
                ->where('missions.data.0.chauffeur.id', $driver->id)
                ->where('missions.data.0.chauffeur.name', $driver->name)
                ->where('missions.data.0.client.telephone', $mission->client->telephone)
                ->where('missions.data.0.type', 'convoyage')
                ->where('missions.data.0.prix_estime', 42000)
                ->where('missions.total', 1)
                ->missing('missions.data.0.client_lat')
                ->missing('missions.data.0.destination_lat'));
    }

    public function test_admin_can_view_separate_chauffeur_history_and_filter_missions_by_driver(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = $this->chauffeur('SGK');
        $otherDriver = $this->chauffeur('ZGK');
        $firstMission = $this->request('SGK');
        $firstMission->update([
            'chauffeur_id' => $driver->id,
            'statut' => 'terminee',
            'mission_completed_at' => now()->subDay(),
            'prix_estime' => 35000,
        ]);
        $latestMission = $this->request('SGK', 'convoyage');
        $latestMission->update([
            'chauffeur_id' => $driver->id,
            'statut' => 'terminee',
            'mission_completed_at' => now(),
            'prix_estime' => 42000,
        ]);
        $otherMission = $this->request('ZGK');
        $otherMission->update([
            'chauffeur_id' => $otherDriver->id,
            'statut' => 'terminee',
            'mission_completed_at' => now(),
            'prix_estime' => 30000,
        ]);

        $this->actingAs($admin)
            ->get('/admin/chauffeurs/historique')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('chauffeurs.0.id', $driver->id)
                ->where('chauffeurs.0.missions_terminees_count', 2)
                ->where('chauffeurs.0.montant_estime_missions_terminees', 77000)
                ->where('chauffeurs.0.derniere_mission_terminee_le', $latestMission->mission_completed_at->format('Y-m-d H:i:s')));

        $this->get("/admin/missions/historique?chauffeur_id={$driver->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('chauffeurFiltre.id', $driver->id)
                ->where('missions.total', 2)
                ->where('missions.data.0.id', $latestMission->id));
    }

    public function test_non_admin_cannot_access_admin_mission_history(): void
    {
        $this->actingAs($this->chauffeur('SGK'))
            ->get('/admin/missions/historique')
            ->assertForbidden();
    }

    public function test_chauffeur_can_accept_or_decline_and_cannot_take_two_active_missions(): void
    {
        $driver = $this->chauffeur('SGK');
        $first = $this->request('SGK');
        $second = $this->request('SGK');
        $declined = $this->request('SGK');

        $this->actingAs($driver)
            ->post("/chauffeur/missions/{$first->id}/accepter")
            ->assertRedirect();
        $this->assertDatabaseHas('client_portal_requests', [
            'id' => $first->id,
            'chauffeur_id' => $driver->id,
            'statut' => 'accepte',
        ]);

        $this->post("/chauffeur/missions/{$second->id}/accepter")
            ->assertStatus(409);

        $this->post("/chauffeur/missions/{$declined->id}/refuser")
            ->assertRedirect();
        $this->assertDatabaseHas('client_portal_request_rejections', [
            'client_portal_request_id' => $declined->id,
            'chauffeur_id' => $driver->id,
        ]);

        $otherDriver = $this->chauffeur('SGK');
        $this->actingAs($otherDriver)
            ->post("/chauffeur/missions/{$declined->id}/accepter")
            ->assertRedirect();
    }

    public function test_chauffeur_can_share_position_and_closing_mission_deletes_precise_coordinates(): void
    {
        $driver = $this->chauffeur('SGK');
        $mission = $this->request('SGK');

        $this->actingAs($driver);
        $this->post("/chauffeur/missions/{$mission->id}/accepter")->assertRedirect();
        $this->post("/chauffeur/missions/{$mission->id}/demarrer")->assertRedirect();
        $startedAt = $mission->fresh()->mission_started_at;
        $this->post("/chauffeur/missions/{$mission->id}/demarrer")->assertRedirect();
        $this->assertEquals($startedAt, $mission->fresh()->mission_started_at);
        $this->putJson("/chauffeur/missions/{$mission->id}/position", [
            'latitude' => 5.4,
            'longitude' => -4.1,
            ])
            ->assertOk()
            ->assertJsonPath('position.lat', 5.4);

        $this->assertDatabaseHas('client_portal_request_positions', [
            'client_portal_request_id' => $mission->id,
            'latitude' => 5.4,
        ]);

        $this->assertDatabaseHas('client_portal_requests', [
            'id' => $mission->id,
            'statut' => 'en_route_recuperation',
        ]);

        $this->post("/chauffeur/missions/{$mission->id}/arrive-recuperation")
            ->assertRedirect();
        $this->assertDatabaseHas('client_portal_requests', [
            'id' => $mission->id,
            'statut' => 'vehicule_recupere',
        ]);

        $this->post("/chauffeur/missions/{$mission->id}/demarrer-garage", [
            'vehicule_securise' => true,
            'etat_vehicule_verifie' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('client_portal_requests', [
            'id' => $mission->id,
            'statut' => 'en_route_garage',
        ]);

        $this->putJson("/chauffeur/missions/{$mission->id}/position", [
            'latitude' => 5.32,
            'longitude' => -4.01,
        ])->assertOk();

        $admin = User::factory()->create(['role' => 'admin', 'siege' => 'SGK']);

        $completionResponse = $this->post("/chauffeur/missions/{$mission->id}/terminer")
            ->assertRedirect();

        $this->assertDatabaseHas('client_portal_requests', [
            'id' => $mission->id,
            'statut' => 'vehicule_arrive_au_garage',
            'client_lat' => null,
            'client_lng' => null,
        ]);
        $this->assertDatabaseHas('client_portal_messages', [
            'client_id' => $mission->client_id,
            'message' => 'Votre véhicule est bien arrivé au Garage Kagnan.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'data->mission_id' => $mission->id,
        ]);
        $this->assertGreaterThan(0, $mission->fresh()->distance_parcourue_km);
        $completionResponse->assertSessionHas('missionSummary.distance_parcourue_km', $mission->fresh()->distance_parcourue_km);

        $this->actingAs($admin)
            ->getJson('/admin/notifications/missions')
            ->assertOk()
            ->assertJsonPath('notifications.0.data.mission_id', $mission->id);
        $this->assertDatabaseMissing('client_portal_request_positions', [
            'client_portal_request_id' => $mission->id,
        ]);
    }

    private function chauffeur(string $siege): User
    {
        return User::factory()->create([
            'role' => 'depanneur',
            'siege' => $siege,
        ]);
    }

    private function request(string $siege, string $type = 'depannage'): ClientPortalRequest
    {
        $client = Client::create([
            'nom' => 'Client',
            'prenom' => 'Test',
            'telephone' => fake()->unique()->numerify('07########'),
            'siege' => $siege,
        ]);

        return $client->portalRequests()->create([
            'siege' => $siege,
            'type' => $type,
            'details' => ['lieu' => 'Plateau, Abidjan'],
            'statut' => 'en_attente',
            'client_lat' => 5.32,
            'client_lng' => -4.01,
            'gps_consent_at' => now(),
        ]);
    }
}
