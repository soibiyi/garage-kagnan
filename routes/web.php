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
use Illuminate\Http\Request;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function (Request $request) {
    if (auth()->user()->role === 'admin') {
        return redirect()->route('admin.users.index');
    }
    if (auth()->user()->role === 'magasinier') {
        return redirect()->route('magasinier.pieces.index');
    }

    $search = trim((string) $request->query('search', ''));
    $statut = $request->query('statut', '');

    $interventionsAtelier = Intervention::duSiege()
        ->with(['vehicule.client', 'receptionniste', 'mecanicien'])
        ->when($statut !== '', fn ($q) => $q->where('statut', $statut))
        ->when($search !== '', function ($q) use ($search) {
            $like = '%' . $search . '%';
            $q->where(function ($q) use ($like) {
                $q->where('numero_ot', 'like', $like)
                  ->orWhereHas('vehicule', function ($v) use ($like) {
                      $v->where('immatriculation', 'like', $like)
                        ->orWhere('marque', 'like', $like)
                        ->orWhere('modele', 'like', $like)
                        ->orWhereHas('client', fn ($c) => $c
                            ->where('nom', 'like', $like)
                            ->orWhere('prenom', 'like', $like));
                  });
            });
        })
        ->latest('date_reception')
        ->paginate(10)
        ->withQueryString();

    $users = \App\Models\User::all();

    $stats = [
        'chiffre_affaires' => '0 FCFA',
        'nombre_voitures' => \App\Models\Vehicule::whereHas('interventions', fn ($q) => $q->duSiege())->count(),
        'nombre_clients' => \App\Models\Client::whereHas('vehicules.interventions', fn ($q) => $q->duSiege())->count(),
    ];

    return Inertia::render('Dashboard', [
        'interventionsAtelier' => $interventionsAtelier,
        'filters' => ['search' => $search, 'statut' => $statut],
        'statuts' => Intervention::duSiege()->distinct()->orderBy('statut')->pluck('statut'),
        'users' => $users,
        'stats' => $stats,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/siege', [UserController::class, 'changerSiege'])->name('siege.switch');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy'); 
    Route::get('/charge-clients/{user}/activites', [UserController::class, 'chargeClientActivities'])->name('charge_clients.activities');
    Route::get('/users/interactions', [UserController::class, 'interactionIndex'])->name('users.interactionindex');
    Route::get('/vehicules/status', [UserController::class, 'vehiculesStatus'])->name('vehicules.status');
    Route::get('/chiffre-affaires', [UserController::class, 'chiffreAffaires'])->name('chiffre-affaires');
    Route::get('/pieces', [StockController::class, 'adminIndex'])->name('pieces.index');
});

// Routes dédiées au Magasinier
Route::middleware(['auth', 'verified'])->prefix('magasinier')->name('magasinier.')->group(function () {
    Route::get('/pieces', [StockController::class, 'magasinierIndex'])->name('pieces.index');
    Route::put('/pieces/{stock}', [StockController::class, 'magasinierUpdate'])->name('pieces.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reception/create', [ReceptionController::class, 'create'])->name('reception.create');
    Route::post('/reception', [ReceptionController::class, 'store'])->name('reception.store');
});

Route::middleware(['auth'])->prefix('parc')->name('parc.')->group(function () {
    Route::get('/', [VehiculeParcController::class, 'index'])->name('index');
    Route::get('/{id}', [VehiculeParcController::class, 'show'])->name('show');
    Route::patch('/{id}/avancer', [VehiculeParcController::class, 'updateProgress'])->name('progress'); 
    Route::patch('/{id}/vehicule', [VehiculeParcController::class, 'updateVehicule'])->name('vehicule.update');
    Route::patch('/{id}/client', [VehiculeParcController::class, 'updateClient'])->name('client.update');
    Route::delete('/{id}', [VehiculeParcController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'verified'])->prefix('suivi-client')->name('charge_client.')->group(function () {
    Route::get('/clients', [ChargeClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{client}', [ChargeClientController::class, 'showClient'])->name('clients.show');
    Route::get('/vehicules/{vehicule}', [ChargeClientController::class, 'showVehicule'])->name('vehicules.show');
    Route::get('/vehicules/{vehicule}/infos', [ChargeClientController::class, 'infosVehicule'])->name('vehicules.infos');
    Route::get('/vehicules/{vehicule}/relances', [ChargeClientController::class, 'relancesVehicule'])->name('vehicules.relances');
    Route::get('/vehicules/{vehicule}/interventions', [ChargeClientController::class, 'interventionsVehicule'])->name('vehicules.interventions');
    Route::patch('/interventions/{intervention}/statut', [ChargeClientController::class, 'updateStatut'])->name('vehicules.update-statut');
    Route::post('/vehicules/{vehicule}/interactions', [ChargeClientController::class, 'storeInteraction'])->name('vehicules.interactions.store');
});

Route::middleware(['auth'])->prefix('administration')->name('administration.')->group(function () {
    Route::get('/dossiers', [DossierController::class, 'index'])->name('dossiers.index');
    Route::get('/dossiers/{dossier}', [DossierController::class, 'show'])->name('dossiers.show');
    Route::post('/dossiers/{dossier}/devis', [DossierController::class, 'storeDevis'])->name('devis.store');
    Route::get('/dossiers/{dossier}/devis/modifier', [DossierController::class, 'editDevis'])->name('devis.edit');
    Route::put('/dossiers/{dossier}/devis', [DossierController::class, 'updateDevis'])->name('devis.update');

    Route::get('/facturation', [DossierController::class, 'facturationIndex'])->name('facturation.index');
    Route::get('/facturation/{dossier}', [DossierController::class, 'facturationShow'])->name('facturation.show');
    Route::post('/facturation/{dossier}/valider-devis', [DossierController::class, 'updateDevisValidation'])->name('facturation.valider-devis');

    Route::get('/devis', [DossierController::class, 'devisIndex'])->name('devis.index');
    Route::get('/devis-acceptes', [DossierController::class, 'devisAcceptesIndex'])->name('devis.acceptes');
    Route::get('/devis-historique', [DossierController::class, 'devisHistoriqueIndex'])->name('devis.historique');

    Route::get('/interventions/{intervention}/devis/pdf', [DossierController::class, 'downloadDevisPdf'])->name('devis.pdf');
    Route::get('/interventions/{intervention}/devis/print', [DossierController::class, 'printDevis'])->name('devis.print');

    Route::get('/devis-directs', [DossierController::class, 'devisDirectIndex'])->name('devis.directs.index');
    Route::get('/devis-directs/create', [DossierController::class, 'devisDirectCreate'])->name('devis.directs.create');
    Route::post('/devis-directs', [DossierController::class, 'storeDevisDirect'])->name('devis.directs.store');

    Route::get('/dossiers/{dossier}/rechercher-pieces', [DossierController::class, 'rechercherPieces'])->name('dossiers.rechercher-pieces');
    Route::get('/devis-directs/rechercher-pieces', [DossierController::class, 'rechercherPieces'])->name('devis.directs.rechercher-pieces');

    Route::get('/stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::post('/stocks', [StockController::class, 'store'])->name('stocks.store');
    Route::put('/stocks/{stock}', [StockController::class, 'update'])->name('stocks.update');
    Route::delete('/stocks/{stock}', [StockController::class, 'destroy'])->name('stocks.destroy');
    Route::get('/stocks/export', [StockController::class, 'export'])->name('stocks.export');
    Route::post('/stocks/import', [StockController::class, 'import'])->name('stocks.import');

    Route::get('/chiffre-affaires', [UserController::class, 'chiffreAffaires'])->name('chiffre-affaires');

    Route::get('/factures/export', [FactureController::class, 'export'])->name('factures.export');
    Route::get('/factures', [FactureController::class, 'index'])->name('factures.index');
    Route::get('/factures/{id}', [FactureController::class, 'show'])->name('factures.show');
    Route::post('/factures/{id}/encaisser', [FactureController::class, 'storeEncaissement'])->name('factures.encaisser');
    Route::delete('/factures/{id}', [FactureController::class, 'destroy'])->name('factures.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/mecanicien/interventions', [MecanicienController::class, 'index'])->name('mecanicien.index');
    Route::patch('/mecanicien/interventions/{intervention}/progres', [MecanicienController::class, 'progress'])->name('mecanicien.progress');
});

require __DIR__.'/auth.php';