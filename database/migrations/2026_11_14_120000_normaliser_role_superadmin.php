<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un super administrateur est stocké role = 'admin' + is_superadmin = true
 * (il hérite ainsi de tous les droits admin de l'application).
 * Corrige les comptes créés à la main avec role = 'superadmin', qui tombaient
 * sur l'écran « Aucun espace n'est encore associé à votre rôle ».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'superadmin')
            ->update(['role' => 'admin', 'is_superadmin' => true, 'siege' => null]);
    }

    public function down(): void
    {
        // Rien à défaire : l'ancien format n'était pas pris en charge par l'application.
    }
};
