<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Reception\ReceptionController; // Import du contrôleur Réceptionniste
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Reception\VehiculeParcController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
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
});

require __DIR__.'/auth.php';