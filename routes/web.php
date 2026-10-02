<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Reception\ReceptionController; 
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Reception\VehiculeParcController;
use App\Http\Controllers\Administrative\DossierController;
use App\Models\Intervention; 
use App\Http\Controllers\Mecanicien\MecanicienController;
use App\Http\Controllers\Administrative\StockController;
use App\Http\Controllers\ChargeClientController; 
use App\Http\Controllers\Administrative\FactureController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Route du dashboard avec redirection automatique pour l'admin
Route::get('/dashboard', function () {
    if (auth()->user()->role === 'admin') {
        return redirect()->route('admin.users.index');
    }

    $interventionsAtelier = Intervention::duSiege()->with(['vehicule.client', 'receptionniste', 'mecanicien'])
        ->whereIn('statut', ['atelier', 'en_cours', 'attente_accord'])
        ->latest('date_reception')
        ->get();

    $users = \App\Models\User::all();
    
    $stats = [
        'chiffre_affaires' => '0 FCFA', 
        // Compteurs limités aux véhicules / clients ayant un dossier dans le siège de l'utilisateur
        'nombre_voitures' => \App\Models\Vehicule::whereHas('interventions', fn ($q) => $q->duSiege())->count(),
        'nombre_clients' => \App\Models\Client::whereHas('vehicules.interventions', fn ($q) => $q->duSiege())->count(),
    ];

    return Inertia::render('Dashboard', [
        'interventionsAtelier' => $interventionsAtelier,
        'users' => $users,
        'stats' => $stats,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// Routes de profil (Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ==========================================
// Routes Administrateur (Gestion des employés & rôles)
// ==========================================
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy'); 
    Route::get('/charge-clients/{user}/activites', [UserController::class, 'chargeClientActivities'])->name('charge_clients.activities');
    Route::get('/users/interactions', [UserController::class, 'interactionIndex'])->name('users.interactionindex');

    // NOUVELLE ROUTE : Liste et statut de tous les véhicules enregistrés
    Route::get('/vehicules/status', [UserController::class, 'vehiculesStatus'])->name('vehicules.status');

     // Historique du chiffre d'affaires par année
    Route::get('/chiffre-affaires', [UserController::class, 'chiffreAffaires'])->name('chiffre-affaires');
});

// ==========================================
// Routes Réceptionniste (Accueil client & véhicule)
// ==========================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reception/create', [ReceptionController::class, 'create'])->name('reception.create');
    Route::post('/reception', [ReceptionController::class, 'store'])->name('reception.store');
});

// Routes pour la gestion du parc véhicules
Route::middleware(['auth'])->prefix('parc')->name('parc.')->group(function () {
    Route::get('/', [VehiculeParcController::class, 'index'])->name('index');
    Route::get('/{id}', [VehiculeParcController::class, 'show'])->name('show');
    Route::patch('/{id}/avancer', [VehiculeParcController::class, 'updateProgress'])->name('progress'); 
});

// ==========================================
// Routes Chargé de Suivi Client
// ==========================================
Route::middleware(['auth', 'verified'])->prefix('suivi-client')->name('charge_client.')->group(function () {
    Route::get('/clients', [ChargeClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{client}', [ChargeClientController::class, 'showClient'])->name('clients.show');

    // Fiche véhicule (accueil : 3 cartes + échéances)
    Route::get('/vehicules/{vehicule}', [ChargeClientController::class, 'showVehicule'])->name('vehicules.show');

    // Pages dédiées du véhicule
    Route::get('/vehicules/{vehicule}/infos', [ChargeClientController::class, 'infosVehicule'])->name('vehicules.infos');
    Route::get('/vehicules/{vehicule}/relances', [ChargeClientController::class, 'relancesVehicule'])->name('vehicules.relances');
    Route::get('/vehicules/{vehicule}/interventions', [ChargeClientController::class, 'interventionsVehicule'])->name('vehicules.interventions');

    // Mise à jour du statut (Bouton Véhicule Livré)
    Route::patch('/interventions/{intervention}/statut', [ChargeClientController::class, 'updateStatut'])
        ->name('vehicules.update-statut');

    // Enregistrement d'une interaction liée à un véhicule
    Route::post('/vehicules/{vehicule}/interactions', [ChargeClientController::class, 'storeInteraction'])
        ->name('vehicules.interactions.store');
});

// ==========================================
// Routes Administration (Dossiers, Devis & Stocks)
// ==========================================
Route::middleware(['auth'])->prefix('administration')->name('administration.')->group(function () {
    Route::get('/dossiers', [DossierController::class, 'index'])->name('dossiers.index');
    Route::get('/dossiers/{dossier}', [DossierController::class, 'show'])->name('dossiers.show');
    Route::post('/dossiers/{dossier}/devis', [DossierController::class, 'storeDevis'])->name('devis.store');

    // FACTURATION & RÈGLEMENTS
    Route::get('/facturation', [DossierController::class, 'facturationIndex'])->name('facturation.index');
    Route::get('/facturation/{dossier}', [DossierController::class, 'facturationShow'])->name('facturation.show');
    Route::post('/facturation/{dossier}/valider-devis', [DossierController::class, 'updateDevisValidation'])->name('facturation.valider-devis');

    // Route globale pour voir TOUS les devis
    Route::get('/devis', [DossierController::class, 'devisIndex'])->name('devis.index');

    // Nouvelles routes
    Route::get('/devis-acceptes', [DossierController::class, 'devisAcceptesIndex'])->name('devis.acceptes');
    Route::get('/devis-historique', [DossierController::class, 'devisHistoriqueIndex'])->name('devis.historique');

    Route::get('/interventions/{intervention}/devis/pdf', [DossierController::class, 'downloadDevisPdf'])->name('devis.pdf');
    Route::get('/interventions/{intervention}/devis/print', [DossierController::class, 'printDevis'])->name('devis.print');

    // Routes pour les devis directs
    Route::get('/devis-directs', [DossierController::class, 'devisDirectIndex'])->name('devis.directs.index');
    Route::get('/devis-directs/create', [DossierController::class, 'devisDirectCreate'])->name('devis.directs.create');
    Route::post('/devis-directs', [DossierController::class, 'storeDevisDirect'])->name('devis.directs.store');

    Route::get('/dossiers/{dossier}/rechercher-pieces', [DossierController::class, 'rechercherPieces'])
        ->name('dossiers.rechercher-pieces');

    Route::get('/devis-directs/rechercher-pieces', [DossierController::class, 'rechercherPieces'])
        ->name('devis.directs.rechercher-pieces');

    // GESTION DES STOCKS (CRUD + EXPORT/IMPORT EXCEL)
    Route::get('/stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::post('/stocks', [StockController::class, 'store'])->name('stocks.store');
    Route::put('/stocks/{stock}', [StockController::class, 'update'])->name('stocks.update');
    Route::delete('/stocks/{stock}', [StockController::class, 'destroy'])->name('stocks.destroy');
    Route::get('/stocks/export', [StockController::class, 'export'])->name('stocks.export');
    Route::post('/stocks/import', [StockController::class, 'import'])->name('stocks.import');

    // FACTURES & ENCAISSEMENT
    Route::get('/factures', [FactureController::class, 'index'])->name('factures.index');
    Route::get('/factures/{id}', [FactureController::class, 'show'])->name('factures.show');
    Route::post('/factures/{id}/encaisser', [FactureController::class, 'storeEncaissement'])->name('factures.encaisser');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/mecanicien/interventions', [MecanicienController::class, 'index'])->name('mecanicien.index');
    Route::patch('/mecanicien/interventions/{intervention}/progres', [MecanicienController::class, 'progress'])->name('mecanicien.progress');
});

require __DIR__.'/auth.php';