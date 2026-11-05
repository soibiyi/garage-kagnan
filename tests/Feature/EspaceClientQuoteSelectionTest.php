<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\LigneDevis;
use App\Models\Paiement;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspaceClientQuoteSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_save_selected_quote_lines_for_their_dossier(): void
    {
        $client = $this->registerClient('0700000101');
        $devis = $this->createQuoteFor($client);
        $accepted = $devis->lignes()->first();
        $rejected = $devis->lignes()->latest('id')->first();

        $this->patchJson("/espace-client/api/devis/{$devis->id}/choix", [
            'lignes_acceptees' => [$accepted->id],
        ])->assertOk()
            ->assertJson(['message' => 'Votre choix a été enregistré.']);

        $this->assertDatabaseHas('lignes_devis', [
            'id' => $accepted->id,
            'is_accepted' => true,
        ]);
        $this->assertDatabaseHas('lignes_devis', [
            'id' => $rejected->id,
            'is_accepted' => false,
        ]);
        $this->assertDatabaseHas('devis', [
            'id' => $devis->id,
            'statut' => 'accepte',
        ]);
        $this->assertDatabaseHas('interventions', [
            'id' => $devis->intervention_id,
            'statut' => 'accepte',
        ]);

        $this->getJson('/espace-client/api/me')
            ->assertOk()
            ->assertJsonPath('client.vehicules.0.interventions.0.devis.lignes.0.id', $accepted->id)
            ->assertJsonPath('client.vehicules.0.interventions.0.devis.lignes.0.is_accepted', true);
    }

    public function test_client_cannot_change_another_clients_quote_or_submit_foreign_lines(): void
    {
        $client = $this->registerClient('0700000102');
        $otherClient = $this->createClient('0700000103');
        $ownQuote = $this->createQuoteFor($client);
        $otherQuote = $this->createQuoteFor($otherClient);
        $ownLine = $ownQuote->lignes()->first();
        $otherLine = $otherQuote->lignes()->first();

        $this->patchJson("/espace-client/api/devis/{$otherQuote->id}/choix", [
            'lignes_acceptees' => [$otherLine->id],
        ])->assertNotFound();

        $this->patchJson("/espace-client/api/devis/{$ownQuote->id}/choix", [
            'lignes_acceptees' => [$otherLine->id],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('lignes_devis', [
            'id' => $ownLine->id,
            'is_accepted' => true,
        ]);
        $this->assertDatabaseHas('devis', [
            'id' => $ownQuote->id,
            'statut' => 'en_attente',
        ]);
    }

    public function test_client_cannot_change_quote_lines_after_a_payment_has_been_recorded(): void
    {
        $client = $this->registerClient('0700000104');
        $devis = $this->createQuoteFor($client);
        $line = $devis->lignes()->first();
        $facture = Facture::forceCreate([
            'intervention_id' => $devis->intervention_id,
            'devis_id' => $devis->id,
            'numero_facture' => 'FAC-CLIENT-001',
            'montant_total' => 11800,
        ]);
        Paiement::create([
            'facture_id' => $facture->id,
            'montant' => 5000,
            'mode_paiement' => 'especes',
        ]);

        $this->patchJson("/espace-client/api/devis/{$devis->id}/choix", [
            'lignes_acceptees' => [],
        ])->assertStatus(409);

        $this->assertDatabaseHas('lignes_devis', [
            'id' => $line->id,
            'is_accepted' => true,
        ]);
        $this->assertDatabaseHas('devis', [
            'id' => $devis->id,
            'statut' => 'en_attente',
        ]);
    }

    private function registerClient(string $telephone): Client
    {
        $this->postJson('/espace-client/inscription', [
            'prenom' => 'Client',
            'nom' => 'Test',
            'telephone' => $telephone,
            'siege' => 'SGK',
            'password' => 'MotDePasseSolide2026',
            'password_confirmation' => 'MotDePasseSolide2026',
        ])->assertCreated();

        return Client::where('telephone', $telephone)->firstOrFail();
    }

    private function createClient(string $telephone): Client
    {
        $client = Client::create([
            'nom' => 'Autre',
            'prenom' => 'Client',
            'telephone' => $telephone,
        ]);
        $client->forceFill([
            'portal_password_change_required' => false,
            'portal_registered' => true,
            'siege' => 'SGK',
        ])->save();

        return $client;
    }

    private function createQuoteFor(Client $client): Devis
    {
        $vehicle = $client->vehicules()->create([
            'immatriculation' => 'QTE-'.$client->telephone,
            'marque' => 'Toyota',
            'modele' => 'Corolla',
        ]);
        $intervention = Intervention::create([
            'vehicule_id' => $vehicle->id,
            'kilometrage' => 10000,
            'statut' => 'attente_accord',
            'siege' => 'SGK',
        ]);
        $devis = Devis::create([
            'intervention_id' => $intervention->id,
            'statut' => 'en_attente',
        ]);

        foreach (['Pneu', 'Filtre'] as $index => $designation) {
            LigneDevis::create([
                'devis_id' => $devis->id,
                'designation' => $designation,
                'quantite' => 1,
                'pu_net' => 10000,
                'remise' => 0,
                'montant_ht' => 10000,
                'montant_ttc' => 11800,
                'famille' => 'Roues & Pneumatiques',
                'is_accepted' => $index === 0,
            ]);
        }

        return $devis->load('lignes');
    }
}
