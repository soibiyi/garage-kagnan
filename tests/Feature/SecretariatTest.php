<?php

namespace Tests\Feature;

use App\Models\SecretaireMessage;
use App\Models\SecretaireTache;
use App\Models\User;
use App\Notifications\SecretaireTacheAssignee;
use App\Notifications\SecretaireTacheTerminee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecretariatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $secretaire;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->secretaire = User::factory()->create(['role' => 'secretaire', 'siege' => 'SGK']);
    }

    private function creerTache(array $etapes = ['Appeler le fournisseur', 'Envoyer le bon de commande']): SecretaireTache
    {
        $this->actingAs($this->admin)
            ->post(route('admin.secretariat.taches.store'), [
                'secretaire_id' => $this->secretaire->id,
                'titre' => 'Commander les filtres',
                'instructions' => 'Voir avec le fournisseur habituel.',
                'echeance' => now()->addDay()->toIso8601String(),
                'priorite' => 'haute',
                'etapes' => array_map(fn ($l) => ['libelle' => $l], $etapes),
            ])
            ->assertRedirect(route('admin.secretariat.index', ['secretaire' => $this->secretaire->id]));

        return SecretaireTache::latest('id')->firstOrFail();
    }

    public function test_admin_assigns_task_with_checklist_and_secretaire_is_notified(): void
    {
        $tache = $this->creerTache();

        $this->assertSame($this->admin->id, $tache->admin_id);
        $this->assertSame('a_faire', $tache->statut);
        $this->assertCount(2, $tache->items);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->secretaire->id,
            'type' => SecretaireTacheAssignee::class,
        ]);

        $this->actingAs($this->secretaire)
            ->get(route('secretaire.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secretaire/Index')
                ->has('taches', 1)
                ->where('taches.0.titre', 'Commander les filtres')
                ->has('taches.0.items', 2));
    }

    public function test_task_cannot_be_assigned_to_a_non_secretaire(): void
    {
        $mecanicien = User::factory()->create(['role' => 'mecanicien']);

        $this->actingAs($this->admin)
            ->post(route('admin.secretariat.taches.store'), [
                'secretaire_id' => $mecanicien->id,
                'titre' => 'Test',
                'echeance' => now()->addDay()->toIso8601String(),
                'priorite' => 'normale',
            ])
            ->assertSessionHasErrors('secretaire_id');
    }

    public function test_checking_all_steps_completes_task_and_notifies_admins(): void
    {
        $tache = $this->creerTache();
        [$premier, $second] = $tache->items->all();

        $this->actingAs($this->secretaire)->patch(route('secretaire.items.toggle', $premier))->assertRedirect();
        $this->assertSame('en_cours', $tache->fresh()->statut);

        $this->actingAs($this->secretaire)->patch(route('secretaire.items.toggle', $second))->assertRedirect();
        $tache->refresh();
        $this->assertSame('terminee', $tache->statut);
        $this->assertNotNull($tache->terminee_at);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->admin->id,
            'type' => SecretaireTacheTerminee::class,
        ]);
    }

    public function test_task_without_steps_can_be_marked_done_directly(): void
    {
        $tache = $this->creerTache([]);

        $this->actingAs($this->secretaire)
            ->patch(route('secretaire.taches.statut', $tache), ['statut' => 'terminee'])
            ->assertRedirect();

        $this->assertSame('terminee', $tache->fresh()->statut);
    }

    public function test_secretaire_cannot_finish_task_with_unchecked_steps(): void
    {
        $tache = $this->creerTache();

        $this->actingAs($this->secretaire)
            ->patch(route('secretaire.taches.statut', $tache), ['statut' => 'terminee'])
            ->assertSessionHas('error');

        $this->assertSame('a_faire', $tache->fresh()->statut);
    }

    public function test_secretaire_can_add_and_remove_only_her_own_steps(): void
    {
        $tache = $this->creerTache();
        $etapeAdmin = $tache->items->first();

        $this->actingAs($this->secretaire)
            ->post(route('secretaire.items.store', $tache), ['libelle' => 'Préparer le devis'])
            ->assertRedirect();
        $etapePerso = $tache->items()->where('ajoute_par_secretaire', true)->firstOrFail();

        $this->actingAs($this->secretaire)->delete(route('secretaire.items.destroy', $etapeAdmin))->assertForbidden();
        $this->actingAs($this->secretaire)->delete(route('secretaire.items.destroy', $etapePerso))->assertRedirect();

        $this->assertDatabaseMissing('secretaire_tache_items', ['id' => $etapePerso->id]);
        $this->assertDatabaseHas('secretaire_tache_items', ['id' => $etapeAdmin->id]);
    }

    public function test_secretaire_cannot_touch_another_secretaires_tasks(): void
    {
        $tache = $this->creerTache();
        $autre = User::factory()->create(['role' => 'secretaire']);

        $this->actingAs($autre)->patch(route('secretaire.items.toggle', $tache->items->first()))->assertForbidden();
        $this->actingAs($autre)->patch(route('secretaire.taches.statut', $tache), ['statut' => 'terminee'])->assertForbidden();
        $this->actingAs($autre)->getJson(route('secretariat.chat.index', $this->secretaire))->assertForbidden();
    }

    public function test_other_roles_cannot_access_secretariat(): void
    {
        $mecanicien = User::factory()->create(['role' => 'mecanicien']);

        $this->actingAs($mecanicien)->get(route('secretaire.index'))->assertForbidden();
        $this->actingAs($mecanicien)->get(route('admin.secretariat.index'))->assertForbidden();
        $this->actingAs($mecanicien)->getJson(route('secretariat.notifications'))->assertForbidden();
        $this->actingAs($this->secretaire)->get(route('admin.secretariat.index'))->assertForbidden();
    }

    public function test_chat_between_admin_and_secretaire_tracks_unread_messages(): void
    {
        $tache = $this->creerTache();

        $this->actingAs($this->secretaire)
            ->postJson(route('secretariat.chat.store', $this->secretaire), [
                'contenu' => 'Le fournisseur est fermé, je relance demain ?',
                'tache_id' => $tache->id,
            ])
            ->assertCreated()
            ->assertJsonPath('message.tache.id', $tache->id)
            ->assertJsonPath('message.est_moi', true);

        $this->actingAs($this->admin)
            ->getJson(route('secretariat.notifications'))
            ->assertJsonPath('messages_non_lus', 1);

        // L'admin ouvre la conversation : le message devient lu
        $this->actingAs($this->admin)
            ->getJson(route('secretariat.chat.index', $this->secretaire))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.est_moi', false);
        $this->assertNotNull(SecretaireMessage::first()->lu_at);

        $this->actingAs($this->admin)
            ->postJson(route('secretariat.chat.store', $this->secretaire), ['contenu' => 'Oui, relance demain matin.'])
            ->assertCreated();

        $this->actingAs($this->secretaire)
            ->getJson(route('secretariat.notifications'))
            ->assertJsonPath('messages_non_lus', 1);
    }

    public function test_chat_rejects_task_of_another_secretaire(): void
    {
        $autre = User::factory()->create(['role' => 'secretaire']);
        $tacheAutre = SecretaireTache::create([
            'secretaire_id' => $autre->id,
            'titre' => 'Autre',
            'echeance' => now()->addDay(),
        ]);

        $this->actingAs($this->secretaire)
            ->postJson(route('secretariat.chat.store', $this->secretaire), [
                'contenu' => 'Test',
                'tache_id' => $tacheAutre->id,
            ])
            ->assertUnprocessable();
    }

    public function test_admin_update_keeps_checked_steps_and_removes_dropped_ones(): void
    {
        $tache = $this->creerTache();
        [$premier, $second] = $tache->items->all();
        $this->actingAs($this->secretaire)->patch(route('secretaire.items.toggle', $premier));

        $this->actingAs($this->admin)
            ->put(route('admin.secretariat.taches.update', $tache), [
                'secretaire_id' => $this->secretaire->id,
                'titre' => 'Commander les filtres (urgent)',
                'echeance' => now()->addHours(3)->toIso8601String(),
                'priorite' => 'haute',
                'etapes' => [['id' => $premier->id, 'libelle' => $premier->libelle]],
            ])
            ->assertRedirect();

        $tache->refresh();
        $this->assertCount(1, $tache->items);
        $this->assertTrue($tache->items->first()->fait);
        $this->assertSame('terminee', $tache->statut);
        $this->assertDatabaseMissing('secretaire_tache_items', ['id' => $second->id]);
    }

    public function test_dashboard_redirects_secretaire_to_her_tasks(): void
    {
        $this->actingAs($this->secretaire)
            ->get(route('dashboard'))
            ->assertRedirect(route('secretaire.index'));
    }
}
