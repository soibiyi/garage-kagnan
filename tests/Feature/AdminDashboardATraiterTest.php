<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeSuppression;
use App\Models\InteractionClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardATraiterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $charge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->charge = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
    }

    private function demande(string $libelle, string $statut = 'en_attente'): DemandeSuppression
    {
        return DemandeSuppression::create([
            'type' => 'dossier',
            'cible_id' => 999999,
            'libelle' => $libelle,
            'motif' => 'Doublon',
            'siege' => 'SGK',
            'demandeur_id' => $this->charge->id,
            'statut' => $statut,
        ]);
    }

    public function test_dashboard_lists_unassigned_clients_and_pending_deletion_requests(): void
    {
        $orphelin = Client::create(['nom' => 'Kone', 'prenom' => 'Awa', 'telephone' => '0701', 'siege' => 'SGK']);
        $suivi = Client::create(['nom' => 'Traore', 'prenom' => 'Ali', 'telephone' => '0702', 'siege' => 'SGK']);
        InteractionClient::create(['client_id' => $suivi->id, 'user_id' => $this->charge->id, 'type' => 'appel']);

        $this->demande('Dossier SGK-001');
        $this->demande('Dossier SGK-002', 'refusee');

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->where('aTraiter.orphelins.total', 1)
                ->where('aTraiter.orphelins.liste.0.id', $orphelin->id)
                ->has('aTraiter.charges', 1)
                ->where('aTraiter.charges.0.id', $this->charge->id)
                ->where('aTraiter.suppressions.total', 1)
                ->where('aTraiter.suppressions.liste.0.libelle', 'Dossier SGK-001')
                ->where('aTraiter.suppressions.liste.0.demandeur.name', $this->charge->name));
    }

    public function test_assigning_and_refusing_from_dashboard_update_the_cards(): void
    {
        $orphelin = Client::create(['nom' => 'Kone', 'prenom' => 'Awa', 'telephone' => '0701', 'siege' => 'SGK']);
        $demande = $this->demande('Dossier SGK-003');

        $this->actingAs($this->admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.clients.attribuer'), ['charge_id' => $this->charge->id, 'client_ids' => [$orphelin->id]])
            ->assertRedirect(route('admin.users.index'));

        $this->actingAs($this->admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.demandes-suppression.refuser', $demande), ['reponse' => 'Dossier encore utile'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('refusee', $demande->fresh()->statut);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('aTraiter.orphelins.total', 0)
                ->where('aTraiter.suppressions.total', 0));
    }
}
