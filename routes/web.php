<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\MissionController;
use App\Http\Controllers\Admin\MissionNotificationController;
use App\Http\Controllers\Reception\ReceptionController; 
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Reception\VehiculeParcController;
use App\Http\Controllers\Administrative\DossierController;
use App\Http\Controllers\Mecanicien\MecanicienController;
use App\Http\Controllers\Administrative\StockController;
use App\Http\Controllers\ChargeClientController; 
use App\Http\Controllers\Administrative\FactureController;
use App\Http\Controllers\EspaceClientController;
use App\Http\Controllers\Chauffeur\ChauffeurController;
use App\Http\Controllers\Admin\SecretariatController;
use App\Http\Controllers\SecretaireController;
use App\Http\Controllers\SecretariatChatController;
use App\Http\Controllers\SuperAdmin\SupervisionController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\SuperAdmin\ErreurController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/espace-client', [EspaceClientController::class, 'index'])->name('espace-client.index');
Route::get('/espace-client/tableau-de-bord', [EspaceClientController::class, 'dashboard'])->name('espace-client.dashboard');
Route::post('/espace-client/inscription', [EspaceClientController::class, 'register'])->middleware('throttle:3,1')->name('espace-client.register');
Route::post('/espace-client/connexion', [EspaceClientController::class, 'login'])->middleware('throttle:5,1')->name('espace-client.login');
Route::post('/espace-client/deconnexion', [EspaceClientController::class, 'logout'])->name('espace-client.logout');
Route::get('/espace-client/api/me', [EspaceClientController::class, 'me'])->name('espace-client.me');
Route::get('/espace-client/api/messages', [EspaceClientController::class, 'portalMessages'])->name('espace-client.messages.index');
Route::post('/espace-client/api/messages', [EspaceClientController::class, 'sendPortalMessage'])->middleware('throttle:30,1')->name('espace-client.messages.store');
Route::patch('/espace-client/api/mot-de-passe', [EspaceClientController::class, 'changePassword'])->name('espace-client.password');
Route::patch('/espace-client/api/profil', [EspaceClientController::class, 'updateProfile'])->name('espace-client.profile');
Route::post('/espace-client/api/vehicules', [EspaceClientController::class, 'addVehicle'])->name('espace-client.vehicles.store');
Route::patch('/espace-client/api/vehicules/{vehicule}', [EspaceClientController::class, 'updateVehicle'])->name('espace-client.vehicles.update');
Route::post('/espace-client/api/vehicules/{vehicule}/reparations', [EspaceClientController::class, 'addExternalRepair'])->name('espace-client.vehicles.repairs.store');
Route::patch('/espace-client/api/devis/{devis}/choix', [EspaceClientController::class, 'updateQuoteSelection'])->name('espace-client.quotes.selection');
Route::post('/espace-client/api/demandes', [EspaceClientController::class, 'storePortalRequest'])->middleware('throttle:10,1')->name('espace-client.requests.store');
Route::get('/espace-client/api/demandes/{portalRequest}/position', [EspaceClientController::class, 'requestPosition'])->name('espace-client.requests.position');

// Tableau de bord : toute la logique (filtres, onglets, compteurs, redirections de rôle) est dans le contrôleur
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/notifications/missions', [MissionNotificationController::class, 'index'])->name('notifications.missions');
    Route::patch('/notifications/missions/{notification}/read', [MissionNotificationController::class, 'markRead'])->name('notifications.missions.read');
    Route::post('/siege', [UserController::class, 'changerSiege'])->name('siege.switch');
    Route::get('/chauffeurs/historique', [MissionController::class, 'chauffeursHistory'])->name('chauffeurs.history');
    Route::get('/missions/historique', [MissionController::class, 'history'])->name('missions.history');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy'); 
    Route::get('/clients', [ChargeClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/gestion', [\App\Http\Controllers\Admin\ClientManagementController::class, 'index'])->name('clients.gestion');
    Route::get('/clients/gestion/{client}', [\App\Http\Controllers\Admin\ClientManagementController::class, 'show'])->name('clients.gestion.show');
    Route::patch('/clients/gestion/{client}/status', [\App\Http\Controllers\Admin\ClientManagementController::class, 'toggleStatus'])->name('clients.gestion.status');
    Route::delete('/clients/gestion/{client}', [\App\Http\Controllers\Admin\ClientManagementController::class, 'destroy'])->name('clients.gestion.destroy');
    Route::get('/charge-clients/{user}/activites', [UserController::class, 'chargeClientActivities'])->name('charge_clients.activities');
    Route::get('/users/interactions', [UserController::class, 'interactionIndex'])->name('users.interactionindex');
    
    // Route pour enregistrer la consigne admin par rapport au rapport d'un client
    Route::post('/clients/{client}/consignes', [UserController::class, 'storeConsigne'])->name('clients.consignes.store');

    Route::get('/vehicules/status', [UserController::class, 'vehiculesStatus'])->name('vehicules.status');
    Route::get('/chiffre-affaires', [UserController::class, 'chiffreAffaires'])->name('chiffre-affaires');
    Route::get('/pieces', [StockController::class, 'adminIndex'])->name('pieces.index');
    // Historique des mouvements pour l'Admin depuis son espace
    Route::get('/stocks/historique', [StockController::class, 'historiqueMouvements'])->name('stocks.historique');
    // Annulation d'une sortie (Admin)
    Route::delete('/stocks/mouvements/{mouvement}', [StockController::class, 'annulerSortie'])->name('stocks.mouvements.annuler');

    // Secrétariat : tâches confiées aux secrétaires
    Route::get('/secretariat', [SecretariatController::class, 'index'])->name('secretariat.index');
    Route::post('/secretariat/taches', [SecretariatController::class, 'store'])->name('secretariat.taches.store');
    Route::put('/secretariat/taches/{tache}', [SecretariatController::class, 'update'])->name('secretariat.taches.update');
    Route::delete('/secretariat/taches/{tache}', [SecretariatController::class, 'destroy'])->name('secretariat.taches.destroy');
});

// Super administrateur : supervision en direct, journal des erreurs, « voir en tant que »
Route::middleware(['auth', 'verified', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\SuperAdmin\AccueilController::class, 'index'])->name('accueil');
    Route::get('/supervision', [SupervisionController::class, 'index'])->name('supervision.index');
    Route::get('/supervision/etat', [SupervisionController::class, 'etat'])->name('supervision.etat');
    Route::get('/supervision/flux', [SupervisionController::class, 'flux'])->name('supervision.flux');
    Route::post('/supervision/voir-en-tant-que/{user}', [SupervisionController::class, 'impersonate'])->name('impersonate');
    Route::get('/erreurs', [ErreurController::class, 'index'])->name('erreurs.index');
    Route::get('/erreurs/{erreur}', [ErreurController::class, 'show'])->name('erreurs.show');
    Route::patch('/erreurs/{erreur}/resoudre', [ErreurController::class, 'resoudre'])->name('erreurs.resoudre');
    Route::patch('/erreurs/{erreur}/rouvrir', [ErreurController::class, 'rouvrir'])->name('erreurs.rouvrir');
    Route::get('/alertes', [ErreurController::class, 'alertes'])->name('alertes');
    Route::get('/petites-fournitures', [\App\Http\Controllers\SuperAdmin\PetitesFournituresController::class, 'index'])->name('petites-fournitures');
    Route::get('/supervision/direct', [SupervisionController::class, 'direct'])->name('supervision.direct');
    Route::patch('/alertes/{notification}/lue', [ErreurController::class, 'marquerLue'])->name('alertes.lue');
});

// Accessibles à tout utilisateur connecté
Route::middleware('auth')->group(function () {
    Route::post('/superadmin/retour', [ImpersonationController::class, 'stop'])->name('superadmin.impersonate.stop');
    Route::post('/erreurs-client', [ErreurController::class, 'client'])->middleware('throttle:20,1')->name('erreurs.client');
});

// Espace de la secrétaire : checklist des tâches reçues
Route::middleware(['auth', 'verified', 'role:secretaire'])->prefix('secretaire')->name('secretaire.')->group(function () {
    Route::get('/taches', [SecretaireController::class, 'index'])->name('index');
    Route::patch('/taches/{tache}/statut', [SecretaireController::class, 'updateStatut'])->name('taches.statut');
    Route::post('/taches/{tache}/etapes', [SecretaireController::class, 'storeItem'])->name('items.store');
    Route::patch('/etapes/{item}', [SecretaireController::class, 'toggleItem'])->name('items.toggle');
    Route::delete('/etapes/{item}', [SecretaireController::class, 'destroyItem'])->name('items.destroy');
});

// Messagerie admin <-> secrétaire et notifications du secrétariat (JSON)
Route::middleware(['auth', 'verified', 'role:admin,secretaire'])->prefix('secretariat')->name('secretariat.')->group(function () {
    Route::get('/chat/{secretaire}/messages', [SecretariatChatController::class, 'messages'])->name('chat.index');
    Route::post('/chat/{secretaire}/messages', [SecretariatChatController::class, 'store'])->middleware('throttle:30,1')->name('chat.store');
    Route::get('/notifications', [SecretariatChatController::class, 'notifications'])->name('notifications');
    Route::patch('/notifications/{notification}/read', [SecretariatChatController::class, 'markRead'])->name('notifications.read');
});

// Routes dédiées au Magasinier
Route::middleware(['auth', 'verified'])->prefix('magasinier')->name('magasinier.')->group(function () {
    Route::get('/pieces', [StockController::class, 'magasinierIndex'])->name('pieces.index');
    Route::put('/pieces/{stock}', [StockController::class, 'magasinierUpdate'])->name('pieces.update');
    // Historique des mouvements pour le Magasinier
    Route::get('/stocks/historique', [StockController::class, 'historiqueMouvements'])->name('stocks.historique');
    // Annulation d'une sortie (Magasinier)
    Route::delete('/stocks/mouvements/{mouvement}', [StockController::class, 'annulerSortie'])->name('stocks.mouvements.annuler');
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
    Route::post('/clients/{client}/portail/mot-de-passe-provisoire', [ChargeClientController::class, 'resetPortalPassword'])->name('clients.portal-password.reset');
    Route::post('/clients/{client}/portail/demandes/{portalRequest}/accepter', [ChargeClientController::class, 'acceptPortalAppointment'])->name('clients.portal-requests.accept');
    Route::get('/clients/{client}/portail/messages', [ChargeClientController::class, 'portalMessages'])->name('clients.portal-messages.index');
    Route::post('/clients/{client}/portail/messages', [ChargeClientController::class, 'sendPortalMessage'])->middleware('throttle:30,1')->name('clients.portal-messages.store');
    Route::get('/clients/{client}/portail/demandes/{portalRequest}/position', [ChargeClientController::class, 'portalRequestPosition'])
        ->name('clients.portal-requests.position');
    Route::get('/vehicules/{vehicule}', [ChargeClientController::class, 'showVehicule'])->name('vehicules.show');
    Route::patch('/vehicules/{vehicule}/echeances', [ChargeClientController::class, 'updateVehiculeEcheances'])->name('vehicules.echeances.update');
    Route::get('/vehicules/{vehicule}/infos', [ChargeClientController::class, 'infosVehicule'])->name('vehicules.infos');
    Route::get('/vehicules/{vehicule}/relances', [ChargeClientController::class, 'relancesVehicule'])->name('vehicules.relances');
    Route::get('/vehicules/{vehicule}/interventions', [ChargeClientController::class, 'interventionsVehicule'])->name('vehicules.interventions');
    Route::patch('/interventions/{intervention}/statut', [ChargeClientController::class, 'updateStatut'])->name('vehicules.update-statut');
    Route::post('/vehicules/{vehicule}/interactions', [ChargeClientController::class, 'storeInteraction'])->name('vehicules.interactions.store');
Route::get('clients/{client}/chat', [EspaceClientController::class, 'chat'])->name('clients.chat');
Route::get('clients/{client}/demandes', [ChargeClientController::class, 'demandes'])->name('clients.demandes');Route::get('clients/{client}/rendez-vous', [EspaceClientController::class, 'rendezVous'])->name('clients.rendez-vous');
Route::post('clients/{client}/portal-requests/{portalRequest}/refuse',
    [EspaceClientController::class, 'refuseRequest'])
    ->name('clients.portal-requests.refuse');

Route::get('notifications/messages',
    [EspaceClientController::class, 'messagesNonLus'])
    ->name('notifications.messages');    
Route::get('notifications/instructions', [\App\Http\Controllers\ChargeClientInstructionNotificationController::class, 'index'])
->name('notifications.instructions');
Route::patch('notifications/instructions/{notification}/read', [\App\Http\Controllers\ChargeClientInstructionNotificationController::class, 'markRead'])
->name('notifications.instructions.read');
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
    Route::get('/stocks/historique', [StockController::class, 'historiqueMouvements'])->name('stocks.historique');
    // Annulation d'une sortie (Administration)
    Route::delete('/stocks/mouvements/{mouvement}', [StockController::class, 'annulerSortie'])->name('stocks.mouvements.annuler');
    
    // Routes d'assistant de réapprovisionnement et bon de commande
    Route::get('/reapprovisionnement', [StockController::class, 'reapprovisionnement'])->name('stocks.reapprovisionnement');
    Route::post('/bon-de-commande', [StockController::class, 'genererBonCommande'])->name('stocks.bon-commande');

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

Route::middleware(['auth', 'verified'])->prefix('chauffeur')->name('chauffeur.')->group(function () {
    Route::get('/missions', [ChauffeurController::class, 'index'])->name('index');
    Route::post('/missions/{portalRequest}/accepter', [ChauffeurController::class, 'accept'])->name('missions.accept');
    Route::post('/missions/{portalRequest}/refuser', [ChauffeurController::class, 'decline'])->name('missions.decline');
    Route::post('/missions/{portalRequest}/demarrer', [ChauffeurController::class, 'start'])->name('missions.start');
    Route::post('/missions/{portalRequest}/arrive-recuperation', [ChauffeurController::class, 'arrivePickup'])->name('missions.arrive-pickup');
    Route::post('/missions/{portalRequest}/demarrer-garage', [ChauffeurController::class, 'startGarage'])->name('missions.start-garage');
    Route::post('/missions/{portalRequest}/terminer', [ChauffeurController::class, 'complete'])->name('missions.complete');
    Route::put('/missions/{portalRequest}/position', [ChauffeurController::class, 'updatePosition'])
        ->middleware('throttle:30,1')
        ->name('missions.position');
});

Route::middleware(['auth', 'verified'])->prefix('depanneur')->name('depanneur.')->group(function () {
    Route::get('/missions', [ChauffeurController::class, 'index'])->name('index');
    Route::post('/missions/{portalRequest}/accepter', [ChauffeurController::class, 'accept'])->name('missions.accept');
    Route::post('/missions/{portalRequest}/refuser', [ChauffeurController::class, 'decline'])->name('missions.decline');
    Route::post('/missions/{portalRequest}/demarrer', [ChauffeurController::class, 'start'])->name('missions.start');
    Route::post('/missions/{portalRequest}/arrive-recuperation', [ChauffeurController::class, 'arrivePickup'])->name('missions.arrive-pickup');
    Route::post('/missions/{portalRequest}/demarrer-garage', [ChauffeurController::class, 'startGarage'])->name('missions.start-garage');
    Route::post('/missions/{portalRequest}/terminer', [ChauffeurController::class, 'complete'])->name('missions.complete');
    Route::put('/missions/{portalRequest}/position', [ChauffeurController::class, 'updatePosition'])
        ->middleware('throttle:30,1')
        ->name('missions.position');
});

require __DIR__.'/auth.php';