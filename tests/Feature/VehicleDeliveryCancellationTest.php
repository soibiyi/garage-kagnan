<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VehicleDeliveryCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_cancel_a_delivery_and_restore_the_previous_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $interventionId = $this->createIntervention('atelier');

        $this->actingAs($admin)
            ->patch(route('charge_client.vehicules.update-statut', $interventionId), ['statut' => 'livre'])
            ->assertRedirect();

        $this->assertDatabaseHas('interventions', [
            'id' => $interventionId,
            'statut' => 'livre',
            'statut_avant_livraison' => 'atelier',
        ]);

        $this->actingAs($admin)
            ->patch(route('charge_client.vehicules.annuler-livraison', $interventionId))
            ->assertRedirect();

        $this->assertDatabaseHas('interventions', [
            'id' => $interventionId,
            'statut' => 'atelier',
            'statut_avant_livraison' => null,
        ]);
    }

    public function test_admin_must_choose_a_return_status_for_an_older_delivery(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $interventionId = $this->createIntervention('livre');

        $this->actingAs($admin)
            ->patch(route('charge_client.vehicules.annuler-livraison', $interventionId))
            ->assertSessionHasErrors('statut');

        $this->actingAs($admin)
            ->patch(route('charge_client.vehicules.annuler-livraison', $interventionId), ['statut' => 'en_cours'])
            ->assertRedirect();

        $this->assertDatabaseHas('interventions', [
            'id' => $interventionId,
            'statut' => 'en_cours',
            'statut_avant_livraison' => null,
        ]);
    }

    public function test_charge_client_cannot_cancel_a_delivery(): void
    {
        $chargeClient = User::factory()->create(['role' => 'charge_client']);
        $interventionId = $this->createIntervention('livre', 'atelier');

        $this->actingAs($chargeClient)
            ->patch(route('charge_client.vehicules.annuler-livraison', $interventionId), ['statut' => 'accepte'])
            ->assertForbidden();

        $this->assertDatabaseHas('interventions', [
            'id' => $interventionId,
            'statut' => 'livre',
            'statut_avant_livraison' => 'atelier',
        ]);
    }

    private function createIntervention(string $statut, ?string $statutAvantLivraison = null): int
    {
        $client = Client::create([
            'nom' => 'Client test',
            'prenom' => 'Test',
            'telephone' => '770000000',
            'siege' => 'SGK',
        ]);
        $vehiculeId = DB::table('vehicules')->insertGetId([
            'client_id' => $client->id,
            'immatriculation' => 'TEST-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('interventions')->insertGetId([
            'vehicule_id' => $vehiculeId,
            'siege' => 'SGK',
            'kilometrage' => 100,
            'circuit' => 'normal',
            'statut' => $statut,
            'statut_avant_livraison' => $statutAvantLivraison,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
