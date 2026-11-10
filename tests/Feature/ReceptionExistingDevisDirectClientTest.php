<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Intervention;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReceptionExistingDevisDirectClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionniste_can_find_and_receive_a_legacy_direct_quote_client_vehicle(): void
    {
        Storage::fake('public');

        $receptionniste = User::factory()->create([
            'role' => 'receptionniste',
            'siege' => 'SGK',
        ]);
        $client = Client::create([
            'nom' => 'Kouassi',
            'prenom' => 'Aminata',
            'telephone' => '0700000000',
            'siege' => null,
        ]);
        $vehicule = Vehicule::create([
            'client_id' => $client->id,
            'immatriculation' => 'GK-123-AB',
            'marque' => 'Toyota',
            'modele' => 'Hilux',
        ]);
        Intervention::create([
            'vehicule_id' => $vehicule->id,
            'siege' => 'SGK',
            'circuit' => 'devis_direct',
            'kilometrage' => 10000,
            'statut' => 'attente_accord',
        ]);

        $this->actingAs($receptionniste)
            ->get(route('reception.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reception/Create')
                ->has('clients', 1)
                ->where('clients.0.id', $client->id)
                ->where('clients.0.vehicules.0.id', $vehicule->id)
                ->where('clients.0.vehicules.0.interventions.0.siege', 'SGK')
            );

        $this->actingAs($receptionniste)
            ->post(route('reception.store'), [
                'client_id' => $client->id,
                'siege' => 'SGK',
                'circuit' => 'normal',
                'immatriculation' => 'GK-123-AB',
                'marque' => 'Toyota',
                'modele' => 'Hilux',
                'kilometrage' => 12000,
                'date_reception' => '2026-11-09',
                'photo_avant' => UploadedFile::fake()->image('avant.jpg'),
                'photo_arriere' => UploadedFile::fake()->image('arriere.jpg'),
                'photo_gauche' => UploadedFile::fake()->image('gauche.jpg'),
                'photo_droite' => UploadedFile::fake()->image('droite.jpg'),
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(1, Vehicule::where('client_id', $client->id)->count());
        $this->assertSame(2, Intervention::where('vehicule_id', $vehicule->id)->count());
        $this->assertDatabaseHas('interventions', [
            'vehicule_id' => $vehicule->id,
            'siege' => 'SGK',
            'circuit' => 'normal',
            'statut' => 'reception',
        ]);
    }

    public function test_receptionniste_does_not_receive_a_client_or_vehicle_from_another_siege(): void
    {
        $receptionniste = User::factory()->create([
            'role' => 'receptionniste',
            'siege' => 'SGK',
        ]);
        $client = Client::create([
            'nom' => 'Traore',
            'prenom' => 'Moussa',
            'telephone' => '0711111111',
            'siege' => null,
        ]);
        $vehicule = Vehicule::create([
            'client_id' => $client->id,
            'immatriculation' => 'ZGK-123-AB',
            'marque' => 'Honda',
            'modele' => 'Civic',
        ]);
        Intervention::create([
            'vehicule_id' => $vehicule->id,
            'siege' => 'ZGK',
            'circuit' => 'devis_direct',
            'kilometrage' => 10000,
        ]);

        $this->actingAs($receptionniste)
            ->get(route('reception.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reception/Create')
                ->has('clients', 0)
            );
    }

    public function test_new_direct_quote_client_is_assigned_to_the_current_siege(): void
    {
        $staff = User::factory()->create([
            'role' => 'charge_client',
            'siege' => 'SGK',
        ]);

        $this->actingAs($staff)
            ->post(route('administration.devis.directs.store'), [
                'nouveau_client_nom' => 'Diallo',
                'nouveau_client_prenom' => 'Awa',
                'nouveau_client_telephone' => '0722222222',
                'nouvelle_marque' => 'Toyota',
                'nouveau_modele' => 'Corolla',
                'nouvelle_immatriculation' => 'SGK-456-CD',
                'kilometrage' => 15000,
                'lignes' => [
                    [
                        'quantite' => 1,
                        'designation' => 'Diagnostic',
                        'pu_net' => 10000,
                    ],
                ],
            ])
            ->assertRedirect(route('dashboard'));

        $client = Client::where('nom', 'Diallo')->firstOrFail();

        $this->assertSame('SGK', $client->siege);
    }

    public function test_receptionniste_can_find_an_online_registered_client_and_receive_again_their_vehicle(): void
    {
        Storage::fake('public');

        $this->postJson('/espace-client/inscription', [
            'prenom' => 'Aminata',
            'nom' => 'Kouassi',
            'telephone' => '0700000030',
            'siege' => 'SGK',
            'password' => 'MotDePasseSolide2026',
            'password_confirmation' => 'MotDePasseSolide2026',
            'vehicle_marque' => 'Toyota',
            'vehicle_modele' => 'Hilux',
            'vehicle_immatriculation' => 'PORTAL-CLIENT-001',
            'vehicle_vin' => 'JT3234567890123456',
            'vehicle_expiration_assurance' => '2027-06-30',
            'vehicle_expiration_sicta' => '2027-09-30',
        ])->assertCreated();

        $client = Client::where('telephone', '0700000030')->firstOrFail();
        $vehicule = $client->vehicules()->firstOrFail();
        $receptionniste = User::factory()->create([
            'role' => 'receptionniste',
            'siege' => 'SGK',
        ]);

        $this->actingAs($receptionniste)
            ->get(route('reception.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reception/Create')
                ->has('clients', 1)
                ->where('clients.0.id', $client->id)
                ->where('clients.0.nom', 'Kouassi')
                ->where('clients.0.prenom', 'Aminata')
                ->where('clients.0.siege', 'SGK')
                ->where('clients.0.vehicules.0.id', $vehicule->id)
                ->where('clients.0.vehicules.0.immatriculation', 'PORTAL-CLIENT-001')
            );

        $this->actingAs($receptionniste)
            ->post(route('reception.store'), [
                'client_id' => $client->id,
                'siege' => 'SGK',
                'circuit' => 'normal',
                'immatriculation' => $vehicule->immatriculation,
                'marque' => $vehicule->marque,
                'modele' => $vehicule->modele,
                'vin' => $vehicule->vin,
                'expiration_assurance' => '2027-06-30',
                'expiration_sicta' => '2027-09-30',
                'kilometrage' => 15000,
                'date_reception' => '2026-11-09',
                'photo_avant' => UploadedFile::fake()->image('avant.jpg'),
                'photo_arriere' => UploadedFile::fake()->image('arriere.jpg'),
                'photo_gauche' => UploadedFile::fake()->image('gauche.jpg'),
                'photo_droite' => UploadedFile::fake()->image('droite.jpg'),
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(1, Vehicule::where('client_id', $client->id)->count());
        $this->assertDatabaseHas('interventions', [
            'vehicule_id' => $vehicule->id,
            'siege' => 'SGK',
            'circuit' => 'normal',
            'statut' => 'reception',
        ]);
    }
}
