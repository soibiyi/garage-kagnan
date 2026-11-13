<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ErrorLog;
use App\Models\User;
use App\Notifications\ErreurApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'admin']);
        $this->superAdmin->forceFill(['is_superadmin' => true])->save();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function payload(array $extra = []): array
    {
        return [
            'name' => 'Nouveau',
            'email' => 'nouveau@example.com',
            'password' => 'secret123',
            'role' => 'admin',
            ...$extra,
        ];
    }

    // ─── Comptes administrateurs ──────────────────────────────────────

    public function test_admin_cannot_create_an_admin_but_superadmin_can(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'nouveau@example.com']);

        $this->actingAs($this->superAdmin)->post(route('admin.users.store'), $this->payload(['role' => 'superadmin']))->assertRedirect();
        $cree = User::where('email', 'nouveau@example.com')->firstOrFail();
        $this->assertSame('admin', $cree->role);
        $this->assertTrue($cree->estSuperAdmin());
    }

    public function test_admin_can_still_create_regular_staff(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->payload(['role' => 'mecanicien']))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'nouveau@example.com', 'role' => 'mecanicien', 'is_superadmin' => false]);
    }

    public function test_admin_cannot_edit_or_delete_another_admin(): void
    {
        $autreAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($this->admin)->get(route('admin.users.edit', $autreAdmin))->assertForbidden();
        $this->actingAs($this->admin)->put(route('admin.users.update', $autreAdmin), [
            'name' => 'Pirate', 'email' => $autreAdmin->email, 'role' => 'admin',
        ])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $autreAdmin))->assertForbidden();
        // Le superadmin est introuvable pour un admin (404, pas 403)
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->superAdmin))->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $autreAdmin->id]);
    }

    public function test_admin_cannot_promote_staff_to_admin(): void
    {
        $meca = User::factory()->create(['role' => 'mecanicien', 'siege' => 'SGK']);

        $this->actingAs($this->admin)->put(route('admin.users.update', $meca), [
            'name' => $meca->name, 'email' => $meca->email, 'role' => 'admin',
        ])->assertForbidden();

        $this->assertSame('mecanicien', $meca->fresh()->role);
    }

    public function test_superadmin_can_edit_and_delete_admins(): void
    {
        $this->actingAs($this->superAdmin)->put(route('admin.users.update', $this->admin), [
            'name' => 'Admin renommé', 'email' => $this->admin->email, 'role' => 'admin',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertSame('Admin renommé', $this->admin->fresh()->name);

        $this->actingAs($this->superAdmin)->delete(route('admin.users.destroy', $this->admin))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $this->admin->id]);
    }

    public function test_last_superadmin_cannot_be_demoted(): void
    {
        $this->actingAs($this->superAdmin)->put(route('admin.users.update', $this->superAdmin), [
            'name' => $this->superAdmin->name, 'email' => $this->superAdmin->email, 'role' => 'admin',
        ])->assertSessionHas('error');

        $this->assertTrue($this->superAdmin->fresh()->estSuperAdmin());
    }

    public function test_superadmin_is_invisible_to_admins(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('users', fn ($users) => collect($users)->pluck('id')->doesntContain($this->superAdmin->id)
                    && collect($users)->pluck('id')->contains($this->admin->id)));

        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->superAdmin))->assertNotFound();
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->superAdmin), [
            'name' => 'X', 'email' => $this->superAdmin->email, 'role' => 'admin',
        ])->assertNotFound();

        // E-mail déjà pris par le superadmin : message neutre, sans dire par qui
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->payload(['role' => 'mecanicien', 'email' => $this->superAdmin->email]))
            ->assertSessionHasErrors(['email' => 'Cette adresse e-mail ne peut pas être utilisée.']);

        // Le superadmin, lui, se voit
        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.accueil'))
            ->assertInertia(fn ($page) => $page
                ->where('admin.users', fn ($users) => collect($users)->pluck('id')->contains($this->superAdmin->id)));
    }

    public function test_role_superadmin_written_by_hand_is_normalised(): void
    {
        // Création via le modèle (tinker, seeder…)
        $cree = User::factory()->create(['role' => 'superadmin', 'siege' => 'SGK']);
        $this->assertSame('admin', $cree->fresh()->role);
        $this->assertTrue($cree->fresh()->estSuperAdmin());
        $this->assertNull($cree->fresh()->siege);

        // Compte déjà en base (SQL / phpMyAdmin) : corrigé par la migration
        $id = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'name' => 'Super Admin', 'email' => 'brut@example.com', 'password' => bcrypt('secret123'),
            'role' => 'superadmin', 'created_at' => now(), 'updated_at' => now(),
        ]);
        (require database_path('migrations/2026_11_14_120000_normaliser_role_superadmin.php'))->up();

        $brut = User::find($id);
        $this->assertTrue($brut->estSuperAdmin());
        $this->actingAs($brut)->get(route('dashboard'))->assertRedirect(route('superadmin.accueil'));
    }

    // ─── Accès à tous les espaces ─────────────────────────────────────

    public function test_superadmin_passes_role_restricted_routes(): void
    {
        // Le tableau de bord admin est intégré à l'accueil du super administrateur
        $this->actingAs($this->superAdmin)->get(route('admin.users.index'))
            ->assertRedirect(route('superadmin.accueil') . '#espace-admin');
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($this->superAdmin)->getJson(route('secretariat.notifications'))->assertOk();
        $this->actingAs($this->superAdmin)->getJson(route('charge_client.notifications.instructions'))->assertOk();
        // Espaces liés à l'identité : redirection vers l'équivalent, jamais de 403
        $this->actingAs($this->superAdmin)->get(route('secretaire.index'))->assertRedirect(route('admin.secretariat.index'));
        $this->actingAs($this->superAdmin)->get(route('chauffeur.index'))->assertRedirect();
    }

    public function test_supervision_is_reserved_to_superadmin(): void
    {
        $this->actingAs($this->admin)->get(route('superadmin.supervision.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('superadmin.erreurs.index'))->assertForbidden();
        $this->actingAs($this->admin)->getJson(route('superadmin.supervision.flux'))->assertForbidden();

        $this->actingAs($this->superAdmin)->get(route('superadmin.supervision.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('superadmin.erreurs.index'))->assertOk();
    }

    // ─── Journal d'activité ───────────────────────────────────────────

    public function test_actions_are_logged_with_sensitive_fields_masked(): void
    {
        $this->actingAs($this->superAdmin)->post(route('admin.users.store'), $this->payload(['role' => 'mecanicien']));

        $log = ActivityLog::where('user_id', $this->superAdmin->id)->where('method', 'POST')->firstOrFail();
        $this->assertSame('admin.users.store', $log->route_name);
        $this->assertSame('••••••', $log->donnees['password']);
        $this->assertSame('Nouveau', $log->donnees['name']);
        $this->assertNotNull($this->superAdmin->fresh()->last_seen_at);
    }

    public function test_polling_requests_are_not_logged(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $this->actingAs($secretaire)->getJson(route('secretariat.notifications'))->assertOk();

        $this->assertSame(0, ActivityLog::count());
        $this->assertNotNull($secretaire->fresh()->last_seen_at);
    }

    public function test_superadmin_follows_a_user_live(): void
    {
        $meca = User::factory()->create(['role' => 'mecanicien', 'siege' => 'SGK']);
        $this->actingAs($meca)->get(route('mecanicien.index'));
        $premier = ActivityLog::where('user_id', $meca->id)->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->getJson(route('superadmin.supervision.flux', ['user' => $meca->id]))
            ->assertOk()
            ->assertJsonCount(1, 'logs')
            ->assertJsonPath('logs.0.description', 'Consulte : Atelier');

        // Rafraîchissement : seulement les nouveautés
        $this->actingAs($meca)->get(route('dashboard'));
        $this->actingAs($this->superAdmin)
            ->getJson(route('superadmin.supervision.flux', ['user' => $meca->id, 'after' => $premier->id]))
            ->assertJsonCount(1, 'logs');

        $this->actingAs($this->superAdmin)
            ->getJson(route('superadmin.supervision.etat'))
            ->assertJsonFragment(['id' => $meca->id, 'en_ligne' => true]);

        // Rafraîchissement en direct : présence + nouvelles actions en un seul appel
        $this->actingAs($this->superAdmin)
            ->getJson(route('superadmin.supervision.direct', ['user' => $meca->id, 'after' => $premier->id]))
            ->assertOk()
            ->assertJsonCount(1, 'logs')
            ->assertJsonFragment(['id' => $meca->id, 'en_ligne' => true]);

        // Le journal arrive avec la page
        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.supervision.index', ['user' => $meca->id]))
            ->assertInertia(fn ($page) => $page->has('logsInitiaux', 2));
    }

    // ─── Voir en tant que ─────────────────────────────────────────────

    public function test_impersonation_round_trip_is_traced(): void
    {
        $chauffeur = User::factory()->create(['role' => 'chauffeur', 'siege' => 'SGK']);

        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.impersonate', $chauffeur))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($chauffeur);
        // « Voir en tant que » est enregistré au nom du super administrateur
        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'superadmin.impersonate',
            'user_id' => $this->superAdmin->id,
            'impersonator_id' => null,
        ]);

        $this->get(route('chauffeur.index'))->assertOk();
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $chauffeur->id,
            'route_name' => 'chauffeur.index',
            'impersonator_id' => $this->superAdmin->id,
        ]);

        $this->post(route('superadmin.impersonate.stop'))->assertRedirect();
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_cannot_impersonate_an_admin_nor_stop_without_impersonating(): void
    {
        $this->actingAs($this->superAdmin)->post(route('superadmin.impersonate', $this->admin))->assertForbidden();

        $meca = User::factory()->create(['role' => 'mecanicien']);
        $this->actingAs($meca)->post(route('superadmin.impersonate.stop'))->assertForbidden();
        $this->actingAs($this->admin)->post(route('superadmin.impersonate', $meca))->assertForbidden();
    }

    // ─── Erreurs ──────────────────────────────────────────────────────

    public function test_server_errors_are_recorded_grouped_and_sent_to_superadmin_first(): void
    {
        Route::middleware('web')->get('/_test/erreur', fn () => throw new \RuntimeException('Boum test'));

        $this->actingAs($this->admin)->get('/_test/erreur')->assertStatus(500);
        $this->actingAs($this->admin)->get('/_test/erreur')->assertStatus(500);

        $erreur = ErrorLog::sole();
        $this->assertSame(\RuntimeException::class, $erreur->type);
        $this->assertSame('Boum test', $erreur->message);
        $this->assertSame(2, $erreur->occurrences);
        $this->assertSame($this->admin->id, $erreur->user_id);

        // Une seule alerte (erreurs identiques regroupées), uniquement pour le superadmin
        $this->assertSame(1, $this->superAdmin->notifications()->where('type', ErreurApplication::class)->count());
        $this->assertSame(0, $this->admin->notifications()->where('type', ErreurApplication::class)->count());

        $this->actingAs($this->superAdmin)
            ->getJson(route('superadmin.alertes'))
            ->assertJsonPath('erreurs_ouvertes', 1)
            ->assertJsonCount(1, 'notifications');

        $this->actingAs($this->superAdmin)->patch(route('superadmin.erreurs.resoudre', $erreur))->assertRedirect();
        $this->assertNotNull($erreur->fresh()->resolue_at);
    }

    public function test_not_found_pages_are_not_reported_as_errors(): void
    {
        $this->actingAs($this->admin)->get('/page-qui-n-existe-pas')->assertNotFound();

        $this->assertSame(0, ErrorLog::count());
    }

    public function test_browser_errors_are_recorded(): void
    {
        $meca = User::factory()->create(['role' => 'mecanicien']);

        $this->actingAs($meca)->postJson(route('erreurs.client'), [
            'message' => "Cannot read properties of undefined (reading 'id')",
            'type' => 'TypeError',
            'url' => 'http://localhost/mecanicien/interventions',
        ])->assertNoContent();

        $this->assertDatabaseHas('error_logs', ['source' => 'client', 'type' => 'TypeError', 'user_id' => $meca->id]);
        $this->assertSame(1, $this->superAdmin->notifications()->count());
    }

    // ─── Accueil du super administrateur ─────────────────────────────

    public function test_superadmin_lands_on_his_home_with_role_cards(): void
    {
        User::factory()->create(['role' => 'secretaire', 'siege' => 'SGK']);
        User::factory()->create(['role' => 'depaneur', 'siege' => 'SGK']);

        $this->actingAs($this->superAdmin)->get(route('dashboard'))->assertRedirect(route('superadmin.accueil'));
        $this->actingAs($this->admin)->get(route('dashboard'))->assertRedirect(route('admin.users.index'));

        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.accueil'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Accueil')
                ->has('comptesParRole.secretaire', 1)
                // ancienne orthographe rangée avec les dépanneurs
                ->has('comptesParRole.depanneur', 1)
                // les administrateurs ne sont pas proposés en « Agir en tant que »
                ->missing('comptesParRole.admin'));

        $this->actingAs($this->admin)->get(route('superadmin.accueil'))->assertForbidden();
    }

    public function test_superadmin_home_embeds_the_admin_dashboard(): void
    {
        User::factory()->create(['role' => 'mecanicien', 'siege' => 'SGK']);

        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.accueil'))
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Accueil')
                ->has('admin.users')
                ->has('admin.stats.chiffre_affaires_mensuel', 12)
                ->has('admin.stats.nombre_voitures')
                ->where('admin.siegeFiltre', 'SGK'));
    }

    public function test_success_message_survives_redirect_to_superadmin_home(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.store'), $this->payload(['role' => 'mecanicien']))
            ->assertRedirect(route('admin.users.index'));

        // admin.users.index → accueil : le message flash est conservé
        $this->get(route('admin.users.index'))->assertRedirect();
        $this->get(route('superadmin.accueil'))
            ->assertInertia(fn ($page) => $page->where('flash.success', 'Utilisateur créé avec succès !'));
    }
}
