<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AdminMiddleware; // 1. N'oubliez pas d'importer votre middleware ici

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            // Journal d'activité + présence (supervision du super administrateur)
            \App\Http\Middleware\TrackUserActivity::class,
        ]);

        // 2. Enregistrement de l'alias du middleware admin
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Chaque erreur serveur est enregistrée et signalée en direct aux super administrateurs.
        // (Les 404, 403, erreurs de validation, etc. ne passent pas ici : Laravel ne les « reporte » pas.)
        $exceptions->report(function (\Throwable $e) {
            \App\Support\ErrorReporter::capturerException($e);
        });
    })->create();