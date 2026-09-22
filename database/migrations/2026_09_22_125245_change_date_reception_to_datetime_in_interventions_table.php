<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            // Modifie la colonne existante pour qu'elle accepte les heures (dateTime)
            $table->dateTime('date_reception')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            // En cas de retour en arrière (rollback)
            $table->date('date_reception')->nullable()->change();
        });
    }
};