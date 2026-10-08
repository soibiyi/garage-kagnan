<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            // Chemins des photos supplémentaires (6 maximum, en plus des 4 photos obligatoires)
            $table->json('photos_supplementaires')->nullable()->after('photo_droite');
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropColumn('photos_supplementaires');
        });
    }
};