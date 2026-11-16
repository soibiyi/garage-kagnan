<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\LigneDevis;
use App\Models\Paiement;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAnnulationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private Intervention $dossier;
    private Devis $devis;
    private Facture $facture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'admin']);
        $this->superAdmin->forceFill(['is_superadmin' => true])->save();
        $this->admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create(['nom' => 'Koné', 'prenom' => 'Awa', 'telephone' => '0701020304']);
        $vehicule = Vehicule::create(['client_id' => $client->id, 'immatriculation' => 'AB-123-CI', 'marque' => 'Toyota', 'modele' => 'Hilux']);
        $this->dossier = Intervention::create(['vehicule_id' => $vehicule->id, 'kilometrage' => 1000, 'siege' => 'SGK', 'statut' => 'facture', 'numero_ot' => 'OT-0042']);
        $this->devis = Devis::create(['intervention_id' => $this->dossier->id, 'statut' => 'accepte', 'montant_total_ttc' => 50000]);
        foreach (['Vidange', 'Filtre à huile'] as $designation) {
            LigneDevis::create(['devis_id' => $this->devis->id, 'designation' => $designation, 'quantite' => 1, 'pu_net' => 25000, 'montant_ht' => 25000, 'montant_ttc' => 25000]);
        }
        $this->facture = Facture::forceCreate(['intervention_id' => $this->dossier->id, 'devis_id' => $this->devis->id, 'numero_facture' => 'FAC-0042',
            'montant_total' => 50000, 'montant_paye' => 20000, 'statut' => 'partiellement_payee', 'created_at' => '2026-11-01 09:30:00']);
        Paiement::forceCreate(['facture_id' => $this->facture->id, 'montant' => 20000, 'mode_paiement' => 'especes']);
    }

    public function test_deleted_dossier_is_traced_with_client_and_vehicle_then_fully_restored(): void
    {
        $this->actingAs($this->admin)->delete(route('parc.destroy', $this->dossier->id))->assertRedirect();

        $this->assertDatabaseMissing('interventions', ['id' => $this->dossier->id]);
        $this->assertSame(0, LigneDevis::count());

        // Chaque élément supprimé est tracé, avec le client et le véhicule, dans une même opération
        $traces = AuditLog::where('evenement', 'supprime')->get();
        $this->assertCount(6, $traces); // paiement, facture, 2 lignes, devis, dossier
        $this->assertCount(1, $traces->pluck('lot')->unique());
        $devisSupprime = $traces->firstWhere('auditable_type', 'Devis');
        $this->assertSame('Awa Koné', $devisSupprime->contexte['client']);
        $this->assertSame('Toyota Hilux', $devisSupprime->contexte['vehicule']);
        $this->assertSame('AB-123-CI', $devisSupprime->contexte['immatriculation']);
        $this->assertSame('OT-0042', $devisSupprime->contexte['numero_ot']);

        // Le journal affiche ces informations et permet de rechercher par client
        $this->actingAs($this->superAdmin)->get(route('superadmin.controle', ['onglet' => 'historique', 'evenement' => 'supprime', 'q' => 'Koné']))
            ->assertInertia(fn ($page) => $page
                ->where('journal.total', 6)
                ->where('journal.data.0.contexte.client', 'Awa Koné')
                ->where('journal.data.0.operation', 6)
                ->where('journal.data.0.annulable', true));

        // Seul le super admin peut annuler
        $this->actingAs($this->admin)->post(route('superadmin.controle.annuler', $devisSupprime))->assertForbidden();

        $this->actingAs($this->superAdmin)->post(route('superadmin.controle.annuler', $devisSupprime))
            ->assertSessionHas('success');

        // Tout est revenu à l'identique (mêmes identifiants, même date de facture)
        $this->assertDatabaseHas('interventions', ['id' => $this->dossier->id, 'numero_ot' => 'OT-0042']);
        $this->assertDatabaseHas('devis', ['id' => $this->devis->id, 'montant_total_ttc' => 50000]);
        $this->assertSame(2, LigneDevis::where('devis_id', $this->devis->id)->count());
        $this->assertSame('2026-11-01 09:30:00', Facture::find($this->facture->id)->created_at->format('Y-m-d H:i:s'));
        $this->assertSame(20000.0, (float) Paiement::where('facture_id', $this->facture->id)->sum('montant'));
        $this->assertSame(6, AuditLog::whereNotNull('annule_le')->where('annule_par', $this->superAdmin->id)->count());

        // Une seconde annulation est refusée
        $this->actingAs($this->superAdmin)->post(route('superadmin.controle.annuler', $devisSupprime))->assertSessionHas('error');
    }

    public function test_deleted_client_with_all_his_history_is_restored_in_one_go(): void
    {
        $clientId = $this->dossier->vehicule->client_id;

        $this->actingAs($this->admin)->delete(route('admin.clients.gestion.destroy', $clientId))->assertRedirect();

        $this->assertDatabaseMissing('clients', ['id' => $clientId]);
        $this->assertSame(0, Paiement::count());

        $traceClient = AuditLog::where('auditable_type', 'Client')->where('evenement', 'supprime')->firstOrFail();
        $this->assertSame('Awa Koné', $traceClient->contexte['client']);
        $this->assertSame('Client supprimé (avec ses véhicules et dossiers)', $traceClient->alerte);
        // client, véhicule, dossier, devis, 2 lignes, facture, paiement : une seule opération
        $this->assertSame(8, AuditLog::where('lot', $traceClient->lot)->count());

        $this->actingAs($this->superAdmin)->post(route('superadmin.controle.annuler', $traceClient))->assertSessionHas('success');

        $this->assertDatabaseHas('clients', ['id' => $clientId, 'nom' => 'Koné', 'telephone' => '0701020304']);
        $this->assertDatabaseHas('vehicules', ['client_id' => $clientId, 'immatriculation' => 'AB-123-CI']);
        $this->assertDatabaseHas('interventions', ['id' => $this->dossier->id]);
        $this->assertSame(2, LigneDevis::where('devis_id', $this->devis->id)->count());
        $this->assertSame(20000.0, (float) Paiement::where('facture_id', $this->facture->id)->sum('montant'));
    }

    public function test_old_traces_without_lot_are_restored_together_and_context_is_retried(): void
    {
        // Traces d'avant la notion d'opération : même auteur, même seconde, sans lot ni contexte
        $this->actingAs($this->admin)->delete(route('parc.destroy', $this->dossier->id));
        AuditLog::query()->update(['lot' => null, 'contexte' => []]);
        $trace = AuditLog::where('auditable_type', 'Facture')->where('evenement', 'supprime')->firstOrFail();

        $this->actingAs($this->superAdmin)->post(route('superadmin.controle.annuler', $trace))->assertSessionHas('success');
        $this->assertDatabaseHas('interventions', ['id' => $this->dossier->id]);
        $this->assertDatabaseHas('factures', ['id' => $this->facture->id]);

        // Le dossier existe de nouveau : le client est retrouvé à l'affichage
        $this->actingAs($this->superAdmin)->get(route('superadmin.controle', ['onglet' => 'alertes']))->assertOk();
        $this->assertSame('Awa Koné', $trace->fresh()->contexte['client'] ?? null);
    }

    public function test_modification_is_reverted_and_conflicts_must_be_forced(): void
    {
        // Modification « suspecte » faite dans une requête
        $this->actingAs($this->admin);
        $this->facture->forceFill(['montant_total' => 30000])->save();
        $trace = AuditLog::where('auditable_type', 'Facture')->where('evenement', 'modifie')->latest('id')->firstOrFail();
        $this->assertSame('Awa Koné', $trace->contexte['client']);

        // Retouchée ensuite par une autre opération
        $autre = AuditLog::create(['auditable_type' => 'Facture', 'auditable_id' => $this->facture->id, 'evenement' => 'modifie', 'libelle' => 'Facture FAC-0042', 'lot' => 'autre-operation']);

        $this->actingAs($this->superAdmin)->post(route('superadmin.controle.annuler', $trace))->assertSessionHasErrors('conflit');
        $this->assertSame(30000.0, (float) $this->facture->fresh()->montant_total);

        $this->actingAs($this->superAdmin)->post(route('superadmin.controle.annuler', $trace), ['forcer' => true])->assertSessionHas('success');
        $this->assertSame(50000.0, (float) $this->facture->fresh()->montant_total);
        $this->assertNull($autre->fresh()->annule_le);
    }
}
