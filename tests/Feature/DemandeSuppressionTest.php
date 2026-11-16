<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeSuppression;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\MvtStock;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandeSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $receptionniste;
    private User $administratif;
    private Intervention $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->receptionniste = User::factory()->create(['role' => 'receptionniste', 'siege' => 'SGK']);
        $this->administratif = User::factory()->create(['role' => 'administratif', 'siege' => 'SGK']);

        $client = Client::create(['nom' => 'Traoré', 'prenom' => 'Ali', 'telephone' => '0700000001']);
        $vehicule = Vehicule::create(['client_id' => $client->id, 'immatriculation' => 'XY-987-CI', 'marque' => 'Nissan', 'modele' => 'Patrol']);
        $this->dossier = Intervention::create(['vehicule_id' => $vehicule->id, 'kilometrage' => 5000, 'siege' => 'SGK', 'statut' => 'facture', 'numero_ot' => 'OT-77']);
        $devis = Devis::create(['intervention_id' => $this->dossier->id, 'statut' => 'accepte']);
        Facture::forceCreate(['intervention_id' => $this->dossier->id, 'devis_id' => $devis->id, 'numero_facture' => 'FAC-77',
            'montant_total' => 80000, 'montant_paye' => 0, 'statut' => 'impayee']);
    }

    public function test_receptionist_only_requests_deletion_and_admin_approves(): void
    {
        // Sans motif : refusé
        $this->actingAs($this->receptionniste)->delete(route('parc.destroy', $this->dossier->id))->assertSessionHasErrors('motif');

        $this->actingAs($this->receptionniste)
            ->delete(route('parc.destroy', $this->dossier->id), ['motif' => 'Dossier créé en double'])
            ->assertSessionHas('success');

        // Rien n'est supprimé, une demande est en attente avec le client et la facture
        $this->assertDatabaseHas('interventions', ['id' => $this->dossier->id]);
        $demande = DemandeSuppression::firstOrFail();
        $this->assertSame('en_attente', $demande->statut);
        $this->assertSame('Ali Traoré', $demande->contexte['client']);
        $this->assertSame('FAC-77', $demande->contexte['facture']);

        // Une seconde demande sur le même dossier n'est pas dupliquée
        $this->actingAs($this->receptionniste)->delete(route('parc.destroy', $this->dossier->id), ['motif' => 'Encore en double']);
        $this->assertSame(1, DemandeSuppression::count());

        // L'admin voit la demande (pastille + page) ; un collaborateur n'y a pas accès
        $this->actingAs($this->admin)->get(route('admin.demandes-suppression.index'))
            ->assertInertia(fn ($page) => $page->where('demandesSuppression', 1)->has('enAttente', 1));
        $this->actingAs($this->receptionniste)->get(route('admin.demandes-suppression.index'))->assertForbidden();
        $this->actingAs($this->receptionniste)->post(route('admin.demandes-suppression.approuver', $demande))->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.demandes-suppression.approuver', $demande))->assertSessionHas('success');

        $this->assertDatabaseMissing('interventions', ['id' => $this->dossier->id]);
        $this->assertDatabaseMissing('factures', ['numero_facture' => 'FAC-77']);
        $this->assertSame('approuvee', $demande->fresh()->statut);
        $this->assertSame($this->admin->id, $demande->fresh()->traite_par);
    }

    public function test_refused_request_keeps_everything(): void
    {
        $this->dossier->update(['numero_ot' => null]); // devis direct
        $this->actingAs($this->administratif)
            ->delete(route('administration.factures.destroy', $this->dossier->id), ['motif' => 'Erreur de saisie client'])
            ->assertSessionHas('success');

        $demande = DemandeSuppression::where('type', 'devis_direct')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.demandes-suppression.refuser', $demande), ['reponse' => 'Facture à conserver'])->assertSessionHas('success');

        $this->assertDatabaseHas('interventions', ['id' => $this->dossier->id]);
        $this->assertSame('refusee', $demande->fresh()->statut);
        $this->assertSame('Facture à conserver', $demande->fresh()->reponse);
    }

    public function test_admin_deletes_directly(): void
    {
        $this->actingAs($this->admin)->delete(route('parc.destroy', $this->dossier->id))->assertSessionHas('success');

        $this->assertDatabaseMissing('interventions', ['id' => $this->dossier->id]);
        $this->assertSame(0, DemandeSuppression::count());
    }

    public function test_only_admins_delete_stock_and_cancel_stock_exits(): void
    {
        $magasinier = User::factory()->create(['role' => 'magasinier', 'siege' => 'SGK']);
        $stock = Stock::forceCreate(['designation_piece' => 'Filtre', 'quantite' => 10]);
        $mouvement = MvtStock::forceCreate(['stock_id' => $stock->id, 'type' => 'sortie', 'quantite' => 1, 'siege' => 'SGK']);

        $this->actingAs($this->administratif)->delete(route('administration.stocks.destroy', $stock))->assertForbidden();
        $this->actingAs($magasinier)->delete(route('magasinier.stocks.mouvements.annuler', $mouvement))->assertForbidden();
        $this->assertDatabaseHas('stocks', ['id' => $stock->id]);
        $this->assertDatabaseHas('mvt_stocks', ['id' => $mouvement->id]);

        $this->actingAs($this->admin)->delete(route('administration.stocks.destroy', $stock))->assertRedirect();
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_only_admins_delete_users(): void
    {
        $this->actingAs($this->receptionniste)->delete(route('admin.users.destroy', $this->administratif))->assertForbidden();
        $this->actingAs($this->receptionniste)->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $this->administratif->id]);
        $this->assertDatabaseHas('users', ['id' => $this->receptionniste->id]);
    }
}
