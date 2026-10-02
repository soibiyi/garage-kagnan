<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Siège de rattachement de l'employé (null = administrateur, accès à tous les sièges)
        Schema::table('users', function (Blueprint $table) {
            $table->string('siege', 3)->nullable()->after('role');
        });

        // Siège d'origine du dossier (détermine le préfixe de l'OT)
        Schema::table('interventions', function (Blueprint $table) {
            $table->string('siege', 3)->nullable()->after('numero_ot');
            $table->index('siege');
        });

        // Les dossiers existants ont tous un OT en SGK-...
        DB::table('interventions')->whereNull('siege')->update(['siege' => 'SGK']);
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropIndex(['siege']);
            $table->dropColumn('siege');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('siege');
        });
    }
};