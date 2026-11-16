<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Notifications\AdminInstructionReceived;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    DB::table('notifications')
        ->where('type', AdminInstructionReceived::class)
        ->whereNotNull('read_at')
        ->where('read_at', '<=', now()->subHours(72))
        ->delete();
})->hourly();

// Journal d'activité : conservé 60 jours
Schedule::call(function () {
    DB::table('activity_logs')->where('created_at', '<', now()->subDays(60))->delete();
})->daily();

/**
 * Crée ou promeut le super administrateur :
 *   php artisan superadmin:creer email@exemple.com --nom="Prénom Nom"
 * Si le compte existe, il est promu ; sinon il est créé et un mot de passe est demandé.
 */
Artisan::command('superadmin:creer {email} {--nom=}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $motDePasse = $this->secret('Mot de passe du nouveau super administrateur (8 caractères minimum)');
        if (! $motDePasse || strlen($motDePasse) < 8) {
            $this->error('Mot de passe trop court.');
            return 1;
        }
        $user = User::create([
            'name' => $this->option('nom') ?: 'Super administrateur',
            'email' => $email,
            'password' => $motDePasse,
            'role' => 'admin',
        ]);
    }

    $user->forceFill(['role' => 'admin', 'siege' => null, 'is_superadmin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
    $this->info("{$user->name} ({$user->email}) est maintenant super administrateur.");

    return 0;
})->purpose('Créer ou promouvoir un super administrateur');


/**
 * Rapports du super administrateur (activables dans Super admin → Système) :
 *   php artisan superadmin:rapport jour|semaine
 */
Artisan::command('superadmin:rapport {periode=jour}', function (string $periode) {
    if (! in_array($periode, ['jour', 'semaine'], true)) {
        $this->error('Période : jour ou semaine.');
        return 1;
    }
    $rapport = \App\Support\RapportBuilder::generer($periode);
    $this->info("Rapport #{$rapport->id} généré et envoyé.");

    return 0;
})->purpose('Générer et envoyer le rapport du super administrateur');

Schedule::command('superadmin:rapport jour')->dailyAt('20:00')
    ->when(fn () => (bool) \App\Support\Parametres::get('rapport_quotidien'));
Schedule::command('superadmin:rapport semaine')->weeklyOn(1, '07:00')
    ->when(fn () => (bool) \App\Support\Parametres::get('rapport_hebdomadaire'));

// Historiques de sécurité et d'audit : conservés 1 an
Schedule::call(function () {
    DB::table('connexions')->where('created_at', '<', now()->subYear())->delete();
    DB::table('audit_logs')->where('created_at', '<', now()->subYear())->whereNull('alerte')->delete();
})->daily();
