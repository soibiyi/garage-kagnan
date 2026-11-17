<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InteractionClient;
use App\Models\User;
use App\Notifications\ClientsAttribues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminClientAttributionTest extends TestCase
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

    private function client(string $nom): Client
    {
        return Client::create(['nom' => $nom, 'prenom' => 'Test', 'telephone' => '0700000000', 'siege' => 'SGK']);
    }

    public function test_admin_sees_assignment_controls_and_assigns_orphans_to_chosen_charge(): void
    {
        $a = $this->client('Kone');
        $b = $this->client('Traore');

        $this->actingAs($this->admin)
            ->get(route('admin.clients.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('peutAttribuer', true)
                ->has('orphelins', 2));

        $this->actingAs($this->admin)
            ->post(route('admin.clients.attribuer'), [
                'charge_id' => $this->charge->id,
                'client_ids' => [$a->id, $b->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 clients attribués à ' . $this->charge->name . '.');

        $this->assertSame(2, InteractionClient::where('type', InteractionClient::TYPE_ATTRIBUTION)
            ->where('user_id', $this->charge->id)->count());

        // Ils ne sont plus « non attribués » et apparaissent chez le chargé choisi
        $this->actingAs($this->admin)
            ->get(route('admin.clients.index'))
            ->assertInertia(fn (Assert $page) => $page->has('orphelins', 0)->has('clients', 2));

        $this->actingAs($this->charge)
            ->get(route('charge_client.clients.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('peutAttribuer', false)
                ->has('clients', 2));

        $this->actingAs($this->charge)->get(route('charge_client.clients.show', $a))->assertOk();
    }

    public function test_already_followed_client_is_not_reassigned(): void
    {
        $suivi = $this->client('Diallo');
        $autre = User::factory()->create(['role' => 'charge_client', 'siege' => 'SGK']);
        InteractionClient::create(['client_id' => $suivi->id, 'user_id' => $autre->id, 'type' => 'appel']);
        $libre = $this->client('Bamba');

        $this->actingAs($this->admin)
            ->post(route('admin.clients.attribuer'), [
                'charge_id' => $this->charge->id,
                'client_ids' => [$suivi->id, $libre->id],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('interaction_clients', ['client_id' => $suivi->id, 'user_id' => $this->charge->id]);
        $this->assertDatabaseHas('interaction_clients', ['client_id' => $libre->id, 'user_id' => $this->charge->id, 'type' => 'attribution']);
    }

    public function test_only_admin_can_assign_and_only_to_a_charge_client(): void
    {
        $client = $this->client('Yao');
        $mecanicien = User::factory()->create(['role' => 'mecanicien']);

        $this->actingAs($this->charge)
            ->post(route('admin.clients.attribuer'), ['charge_id' => $this->charge->id, 'client_ids' => [$client->id]])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.clients.attribuer'), ['charge_id' => $mecanicien->id, 'client_ids' => [$client->id]])
            ->assertSessionHasErrors('charge_id');

        $this->assertDatabaseCount('interaction_clients', 0);
    }

    public function test_charge_is_notified_once_per_attribution_and_link_targets_the_right_page(): void
    {
        $a = $this->client('Kone');
        $b = $this->client('Traore');
        $c = $this->client('Bamba');

        // Attribution groupée : une seule notification pour les 2 clients
        $this->actingAs($this->admin)
            ->post(route('admin.clients.attribuer'), ['charge_id' => $this->charge->id, 'client_ids' => [$a->id, $b->id]]);
        // Attribution d'un seul client
        $this->actingAs($this->admin)
            ->post(route('admin.clients.attribuer'), ['charge_id' => $this->charge->id, 'client_ids' => [$c->id]]);

        $this->assertSame(2, $this->charge->notifications()->where('type', ClientsAttribues::class)->count());

        $reponse = $this->actingAs($this->charge)
            ->getJson(route('charge_client.notifications.instructions'))
            ->assertOk()
            ->assertJsonPath('unread_count', 2);

        $notifs = collect($reponse->json('notifications'))->keyBy(fn ($n) => $n['data']['nb']);
        $this->assertSame('attribution', $notifs[2]['data']['kind']);
        $this->assertSame(['Test Kone', 'Test Traore'], $notifs[2]['data']['client_names']);
        $this->assertSame(route('charge_client.clients.index'), $notifs[2]['url']);
        $this->assertSame('Test Bamba', $notifs[1]['data']['client_name']);
        $this->assertSame(route('charge_client.clients.show', $c->id), $notifs[1]['url']);

        // Ouvrir la notification la marque comme lue
        $this->actingAs($this->charge)
            ->patch(route('charge_client.notifications.instructions.read', $notifs[1]['id']))
            ->assertNoContent();
        $this->actingAs($this->charge)
            ->getJson(route('charge_client.notifications.instructions'))
            ->assertJsonPath('unread_count', 1);
    }

    public function test_no_notification_when_nothing_was_assigned(): void
    {
        $suivi = $this->client('Diallo');
        InteractionClient::create(['client_id' => $suivi->id, 'user_id' => $this->charge->id, 'type' => 'appel']);

        $this->actingAs($this->admin)
            ->post(route('admin.clients.attribuer'), ['charge_id' => $this->charge->id, 'client_ids' => [$suivi->id]])
            ->assertSessionHas('error');

        $this->assertSame(0, $this->charge->notifications()->count());
    }

    public function test_attribution_is_not_counted_as_a_contact_in_team_stats(): void
    {
        $client = $this->client('Ouattara');

        $this->actingAs($this->admin)
            ->post(route('admin.clients.attribuer'), ['charge_id' => $this->charge->id, 'client_ids' => [$client->id]]);

        $this->assertSame(0, InteractionClient::query()->contacts()->count());
        $this->assertSame(1, InteractionClient::count());
    }
}
