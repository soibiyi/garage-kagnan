<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\LigneDevis;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDevisComplementaireEtSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Intervention $dossier;
    private Devis $principal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nom' => 'Kone', 'prenom' => 'Awa', 'telephone' => '0700000000', 'siege' => 'SGK']);
        $vehicule = Vehicule::create(['client_id' => $client->id, 'immatriculation' => 'CMP-001', 'marque' => 'Toyota', 'modele' => 'Hilux']);
        $this->dossier = Intervention::create([
            'vehicule_id' => $vehicule->id,
            'kilometrage' => 10000,
            'siege' => 'SGK',
            'statut' => 'accepte',
        ]);
        $this->principal = $this->devis(false, 100000);
    }

    private function devis(bool $complementaire, int $prix, string $statut = 'accepte'): Devis
    {
        $devis = Devis::create([
            'intervention_id' => $this->dossier->id,
            'statut' => $statut,
            'is_complementaire' => $complementaire,
            'petite_fourniture_active' => false,
        ]);
        LigneDevis::create([
            'devis_id' => $devis->id,
            'quantite' => 1,
            'designation' => $complementaire ? 'Complément' : 'Vidange',
            'pu_net' => $prix,
            'remise' => 0,
            'montant_ht' => $prix,
            'montant_ttc' => $prix,
            'ne_pas_appliquer_tva' => true,
            'is_accepted' => true,
            'type' => 'main_d_oeuvre',
        ]);

        return $devis;
    }

    private function ligne(string $designation, int $prix): array
    {
        return ['quantite' => 1, 'designation' => $designation, 'pu_net' => $prix, 'remise' => 0, 'ne_pas_appliquer_tva' => true, 'is_accepted' => true];
    }

    private function encaisser(int $montant): void
    {
        $this->actingAs($this->admin)
            ->post(route('administration.factures.encaisser', $this->dossier->id), [
                'montant' => $montant,
                'mode_paiement' => 'Especes',
                'date_paiement' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_only_one_complementary_quote_per_dossier(): void
    {
        $lignes = ['lignes' => [$this->ligne('Plaquettes', 20000)], 'petite_fourniture_active' => false];

        $this->actingAs($this->admin)
            ->post(route('administration.devis.complementaire.store', $this->dossier->id), $lignes)
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $this->dossier->devisComplements()->count());

        $complement = $this->dossier->devisComplements()->first();

        // Second essai : refusé, renvoi vers le complément existant
        $this->actingAs($this->admin)
            ->post(route('administration.devis.complementaire.store', $this->dossier->id), $lignes)
            ->assertRedirect(route('administration.facturation.show', ['dossier' => $this->dossier->id, 'devis_id' => $complement->id]))
            ->assertSessionHas('error');
        $this->actingAs($this->admin)
            ->get(route('administration.devis.complementaire.create', $this->dossier->id))
            ->assertRedirect();

        $this->assertSame(1, $this->dossier->devisComplements()->count());
    }

    public function test_admin_modifies_a_validated_complementary_quote_after_a_payment(): void
    {
        $complement = $this->devis(true, 30000);
        $this->encaisser(110000);

        $this->actingAs($this->admin)
            ->get(route('administration.devis.edit', ['dossier' => $this->dossier->id, 'devis_id' => $complement->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('modeEdition', true)
                ->where('devisId', $complement->id)
                ->where('facturation.encaisse', 110000)
                ->where('facturation.total_autres_devis', 100000));

        $this->actingAs($this->admin)
            ->put(route('administration.devis.update', $this->dossier->id), [
                'devis_id' => $complement->id,
                'lignes' => [$this->ligne('Complément', 30000), $this->ligne('Disques', 20000)],
                'petite_fourniture_active' => false,
            ])
            ->assertSessionHasNoErrors();

        // Le devis principal n'a pas bougé, le complément a 2 lignes
        $this->assertCount(1, $this->principal->fresh()->lignes);
        $this->assertCount(2, $complement->fresh()->lignes);

        $facture = Facture::where('intervention_id', $this->dossier->id)->firstOrFail();
        $this->assertEquals(150000, (float) $facture->montant_total);
        $this->assertSame('partiellement_payee', $facture->statut);

        // Ne peut pas descendre sous l'encaissé (100 000 + 5 000 < 110 000)
        $this->actingAs($this->admin)
            ->put(route('administration.devis.update', $this->dossier->id), [
                'devis_id' => $complement->id,
                'lignes' => [$this->ligne('Complément', 5000)],
                'petite_fourniture_active' => false,
            ])
            ->assertSessionHasErrors('lignes');
    }

    public function test_admin_deletes_a_validated_main_quote_with_its_complement(): void
    {
        $complement = $this->devis(true, 30000);

        $this->actingAs($this->admin)
            ->delete(route('administration.devis.destroy', ['dossier' => $this->dossier->id, 'devis' => $this->principal->id]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertNull(Devis::find($this->principal->id));
        $this->assertNull(Devis::find($complement->id));
        $this->assertSame(0, LigneDevis::count());
        $this->assertSame('atelier', $this->dossier->fresh()->statut);
    }

    public function test_admin_deletes_a_pending_complement_only(): void
    {
        $complement = $this->devis(true, 30000, 'en_attente');

        $this->actingAs($this->admin)
            ->delete(route('administration.devis.destroy', ['dossier' => $this->dossier->id, 'devis' => $complement->id]))
            ->assertSessionHas('success');

        $this->assertNull(Devis::find($complement->id));
        $this->assertNotNull(Devis::find($this->principal->id));
        $this->assertSame('accepte', $this->dossier->fresh()->statut);
    }

    public function test_deletion_refused_when_payments_exceed_remaining_total(): void
    {
        $complement = $this->devis(true, 30000);
        $this->encaisser(110000);

        $this->actingAs($this->admin)
            ->delete(route('administration.devis.destroy', ['dossier' => $this->dossier->id, 'devis' => $complement->id]))
            ->assertSessionHas('error');
        $this->actingAs($this->admin)
            ->delete(route('administration.devis.destroy', ['dossier' => $this->dossier->id, 'devis' => $this->principal->id]))
            ->assertSessionHas('error');

        $this->assertNotNull(Devis::find($complement->id));
        $this->assertNotNull(Devis::find($this->principal->id));
    }

    public function test_non_admin_cannot_delete_a_quote(): void
    {
        $administratif = User::factory()->create(['role' => 'administratif', 'siege' => 'SGK']);

        $this->actingAs($administratif)
            ->delete(route('administration.devis.destroy', ['dossier' => $this->dossier->id, 'devis' => $this->principal->id]))
            ->assertForbidden();

        $this->assertNotNull(Devis::find($this->principal->id));
    }
}
