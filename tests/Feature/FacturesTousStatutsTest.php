<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FacturesTousStatutsTest extends TestCase
{
    use RefreshDatabase;

    private function dossier(string $statut, string $immat): Intervention
    {
        $client = Client::create(['nom' => 'Kone', 'prenom' => 'Awa', 'telephone' => '0700000000', 'siege' => 'SGK']);
        $vehicule = Vehicule::create(['client_id' => $client->id, 'immatriculation' => $immat, 'marque' => 'Toyota', 'modele' => 'Hilux']);
        $dossier = Intervention::create(['vehicule_id' => $vehicule->id, 'kilometrage' => 1000, 'siege' => 'SGK', 'statut' => $statut]);
        Devis::create(['intervention_id' => $dossier->id, 'statut' => 'accepte', 'petite_fourniture_active' => false]);

        return $dossier;
    }

    public function test_la_page_factures_affiche_les_dossiers_livres_annules_et_deja_factures(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $accepte = $this->dossier('accepte', 'ACC-001');
        $livre = $this->dossier('livre', 'LIV-001');
        $annule = $this->dossier('annule', 'ANN-001');
        $atelierFacture = $this->dossier('atelier', 'ATE-001');
        $atelierSansFacture = $this->dossier('atelier', 'ATE-002');

        (new Facture)->forceFill([
            'intervention_id' => $atelierFacture->id,
            'devis_id' => $atelierFacture->devis->id,
            'numero_facture' => 'F-TEST-001',
            'montant_total' => 0,
            'montant_paye' => 0,
            'statut' => 'impayee',
        ])->save();

        $this->actingAs($admin)
            ->get(route('administration.factures.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($accepte, $livre, $annule, $atelierFacture, $atelierSansFacture) {
                $ids = collect($page->toArray()['props']['dossiers']['data'])->pluck('id');

                $this->assertTrue($ids->contains($accepte->id));
                $this->assertTrue($ids->contains($livre->id));
                $this->assertTrue($ids->contains($annule->id));
                $this->assertTrue($ids->contains($atelierFacture->id));
                $this->assertFalse($ids->contains($atelierSansFacture->id));
            });
    }
}
