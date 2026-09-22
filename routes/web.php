<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Reception\ReceptionController; // Import du contrôleur Réceptionniste
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Reception\VehiculeParcController;
use App\Http\Controllers\Administrative\DossierController;
use App\Models\Intervention; // <-- 1. Importe le modèle Intervention ici
use App\Http\Controllers\Mecanicien\MecanicienController;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// 2. Modifie la route du dashboard pour récupérer et passer les interventions en atelier
Route::get('/dashboard', function () {
    $interventionsAtelier = Intervention::with(['vehicule.client', 'receptionniste', 'mecanicien'])
        ->whereIn('statut', ['atelier', 'en_cours', 'attente_accord'])
        ->latest('date_reception')
        ->get();

    return Inertia::render('Dashboard', [
        'interventionsAtelier' => $interventionsAtelier,
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

// Routes Administration (Dossiers & Devis)
Route::middleware(['auth'])->prefix('administration')->name('administration.')->group(function () {
    Route::get('/dossiers', [DossierController::class, 'index'])->name('dossiers.index');
    Route::get('/dossiers/{dossier}', [DossierController::class, 'show'])->name('dossiers.show');
    Route::post('/dossiers/{dossier}/devis', [DossierController::class, 'storeDevis'])->name('devis.store');

    // FACTURATION & RÈGLEMENTS (Géré directement dans DossierController)
    Route::get('/facturation', [DossierController::class, 'facturationIndex'])->name('facturation.index');
    Route::get('/facturation/{dossier}', [DossierController::class, 'facturationShow'])->name('facturation.show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/mecanicien/interventions', [MecanicienController::class, 'index'])->name('mecanicien.index');
    Route::patch('/mecanicien/interventions/{intervention}/progres', [MecanicienController::class, 'progress'])->name('mecanicien.progress');
});

require __DIR__.'/auth.php';