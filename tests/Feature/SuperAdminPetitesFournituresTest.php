<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\LigneDevis;
use App\Models\Paiement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminPetitesFournituresTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-13 10:00:00');

        $this->superAdmin = User::factory()->create(['role' => 'admin']);
        $this->superAdmin->forceFill(['is_superadmin' => true])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Dossier facturé : lignes acceptées pour $ttc, petite fourniture auto (3 %) ou saisie. */
    private function dossier(int $ttc, string $siege = 'SGK', ?int $montantSaisi = null, int $paye = 0, ?string $datePaiement = null, ?string $dateAcceptation = null): Intervention
    {
        static $n = 0;
        $n++;
        $client = Client::create(['nom' => "Client{$n}", 'prenom' => 'Test', 'telephone' => "0700000{$n}", 'siege' => $siege]);
        $vehicule = $client->vehicules()->create(['immatriculation' => "PF-{$n}", 'marque' => 'Toyota', 'modele' => 'Hilux']);
        $intervention = Intervention::create(['vehicule_id' => $vehicule->id, 'kilometrage' => 1000, 'statut' => 'accepte', 'siege' => $siege]);
        $devis = Devis::create([
            'intervention_id' => $intervention->id,
            'statut' => 'accepte',
            'petite_fourniture_montant' => $montantSaisi,
        ]);
        LigneDevis::create([
            'devis_id' => $devis->id, 'designation' => 'Vidange', 'quantite' => 1, 'pu_net' => $ttc,
            'remise' => 0, 'montant_ht' => $ttc, 'montant_ttc' => $ttc, 'famille' => 'Entretien', 'is_accepted' => true,
        ]);
        // Ligne refusée : ne compte pas
        LigneDevis::create([
            'devis_id' => $devis->id, 'designation' => 'Refusé', 'quantite' => 1, 'pu_net' => 50000,
            'remise' => 0, 'montant_ht' => 50000, 'montant_ttc' => 50000, 'famille' => 'Entretien', 'is_accepted' => false,
        ]);
        if ($dateAcceptation) {
            $devis->forceFill(['updated_at' => $dateAcceptation])->saveQuietly();
        }

        if ($paye > 0) {
            $total = $montantSaisi !== null ? $ttc + $montantSaisi : (int) round($ttc * 1.03);
            $facture = (new Facture)->forceFill([
                'intervention_id' => $intervention->id, 'devis_id' => $devis->id, 'numero_facture' => "F-{$n}",
                'montant_total' => $total, 'montant_paye' => $paye, 'statut' => $paye >= $total ? 'payee' : 'partiellement_payee',
            ]);
            $facture->save();
            (new Paiement)->forceFill([
                'facture_id' => $facture->id, 'enregistre_par' => $this->superAdmin->id, 'montant' => $paye,
                'mode_paiement' => 'Especes', 'date_paiement' => $datePaiement ?? now(),
            ])->save();
        }

        return $intervention;
    }

    public function test_recap_totals_paid_and_unpaid_by_month_and_week(): void
    {
        // Soldé cette semaine : PF auto 3 % de 100 000 = 3 000
        $this->dossier(100000, paye: 103000, datePaiement: '2026-11-12 09:00:00');
        // Non soldé (acompte) : PF saisie 5 000
        $this->dossier(200000, montantSaisi: 5000, paye: 50000);
        // Non payé du tout, accepté le mois dernier : PF auto 3 % de 50 000 = 1 500
        $this->dossier(50000, dateAcceptation: '2026-10-05 10:00:00');
        // Autre siège, soldé en octobre : PF 3 % de 10 000 = 300
        $this->dossier(10000, siege: 'ZGK', paye: 10300, datePaiement: '2026-10-20 10:00:00', dateAcceptation: '2026-10-18 10:00:00');

        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.petites-fournitures'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/PetitesFournitures')
                ->where('resume.total', 3000 + 5000 + 1500 + 300)
                ->where('resume.soldees', 3000 + 300)
                ->where('resume.non_soldees', 5000 + 1500)
                ->where('resume.nb_factures', 4)
                ->where('resume.nb_soldees', 2)
                // Novembre : facturés 3 000 + 5 000 ; encaissé 3 000
                ->where('parMois.0.cle', '2026-11-01')
                ->where('parMois.0.total', 8000)
                ->where('parMois.0.soldees', 3000)
                ->where('parMois.0.non_soldees', 5000)
                // Octobre : facturés 1 500 + 300 ; encaissé 300
                ->where('parMois.1.total', 1800)
                ->where('parMois.1.soldees', 300)
                // Semaine en cours (9–15 nov.)
                ->where('resume.semaine_en_cours.soldees', 3000)
                ->has('parSemaine', 12)
                ->has('dossiers', 4));
    }

    public function test_siege_filter(): void
    {
        $this->dossier(100000);
        $this->dossier(10000, siege: 'ZGK');

        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.petites-fournitures', ['siege' => 'ZGK']))
            ->assertInertia(fn (Assert $page) => $page->where('resume.total', 300)->has('dossiers', 1));
    }

    public function test_reserved_to_superadmin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('superadmin.petites-fournitures'))->assertForbidden();
    }
}
