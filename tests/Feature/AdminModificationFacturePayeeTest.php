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

class AdminModificationFacturePayeeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Intervention $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nom' => 'Kone', 'prenom' => 'Awa', 'telephone' => '0700000000', 'siege' => 'SGK']);
        $vehicule = Vehicule::create(['client_id' => $client->id, 'immatriculation' => 'FAC-001', 'marque' => 'Toyota', 'modele' => 'Hilux']);
        $this->dossier = Intervention::create([
            'vehicule_id' => $vehicule->id,
            'kilometrage' => 10000,
            'siege' => 'SGK',
            'statut' => 'accepte',
        ]);
        // Sans TVA ni petite fourniture : les montants restent simples à vérifier
        $devis = Devis::create([
            'intervention_id' => $this->dossier->id,
            'statut' => 'accepte',
            'petite_fourniture_active' => false,
        ]);
        LigneDevis::create([
            'devis_id' => $devis->id,
            'quantite' => 1,
            'designation' => 'Vidange',
            'pu_net' => 100000,
            'remise' => 0,
            'montant_ht' => 100000,
            'montant_ttc' => 100000,
            'ne_pas_appliquer_tva' => true,
            'is_accepted' => true,
            'type' => 'main_d_oeuvre',
        ]);

        // Premier versement de 60 000 F
        $this->actingAs($this->admin)
            ->post(route('administration.factures.encaisser', $this->dossier->id), [
                'montant' => 60000,
                'mode_paiement' => 'Especes',
                'date_paiement' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();
    }

    private function ligne(string $designation, int $prix, bool $acceptee = true): array
    {
        return [
            'quantite' => 1,
            'designation' => $designation,
            'pu_net' => $prix,
            'remise' => 0,
            'ne_pas_appliquer_tva' => true,
            'is_accepted' => $acceptee,
        ];
    }

    private function modifier(array $lignes)
    {
        return $this->actingAs($this->admin)->put(route('administration.devis.update', $this->dossier->id), [
            'lignes' => $lignes,
            'petite_fourniture_active' => false,
        ]);
    }

    public function test_admin_adds_an_item_after_a_payment_and_invoice_is_resynchronised(): void
    {
        $this->actingAs($this->admin)
            ->get(route('administration.devis.edit', $this->dossier->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('modeEdition', true)
                ->where('facturation.encaisse', 60000));

        $this->modifier([$this->ligne('Vidange', 100000), $this->ligne('Plaquettes de frein', 50000)])
            ->assertSessionHasNoErrors();

        $facture = Facture::where('intervention_id', $this->dossier->id)->firstOrFail();
        $this->assertEquals(150000, (float) $facture->montant_total);
        $this->assertEquals(60000, (float) $facture->montant_paye);
        $this->assertSame('partiellement_payee', $facture->statut);

        // Le reste à payer suit la nouvelle facture
        $this->actingAs($this->admin)
            ->get(route('administration.factures.show', $this->dossier->id))
            ->assertInertia(fn ($page) => $page
                ->where('resume.total_ttc', 150000)
                ->where('resume.reste', 90000));
    }

    public function test_admin_removes_items_down_to_the_paid_amount_and_invoice_becomes_paid(): void
    {
        $this->modifier([$this->ligne('Vidange', 60000), $this->ligne('Option refusée', 30000, false)])
            ->assertSessionHasNoErrors();

        $facture = Facture::where('intervention_id', $this->dossier->id)->firstOrFail();
        $this->assertEquals(60000, (float) $facture->montant_total);
        $this->assertSame('payee', $facture->statut);
    }

    public function test_total_cannot_drop_below_what_was_already_paid(): void
    {
        $this->modifier([$this->ligne('Vidange', 40000)])
            ->assertSessionHasErrors('lignes');

        // Rien n'a changé : transaction annulée
        $lignes = $this->dossier->fresh()->devis->lignes;
        $this->assertCount(1, $lignes);
        $this->assertEquals(100000, (float) $lignes->first()->pu_net);
        $this->assertEquals(100000, (float) Facture::where('intervention_id', $this->dossier->id)->value('montant_total'));
    }

    public function test_other_roles_still_cannot_modify_a_paid_invoice(): void
    {
        $administratif = User::factory()->create(['role' => 'administratif', 'siege' => 'SGK']);

        $this->actingAs($administratif)
            ->get(route('administration.devis.edit', $this->dossier->id))
            ->assertForbidden();

        $this->actingAs($administratif)
            ->put(route('administration.devis.update', $this->dossier->id), [
                'lignes' => [$this->ligne('Vidange', 200000)],
            ])
            ->assertForbidden();

        $this->assertEquals(100000, (float) $this->dossier->fresh()->devis->lignes->first()->pu_net);
    }
}
