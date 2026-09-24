<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_devis', function (Blueprint $table) {
            // Indique si la ligne a été acceptée par le client (par défaut à true ou false selon votre choix)
            $table->boolean('is_accepted')->default(true)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('lignes_devis', function (Blueprint $table) {
            $table->dropColumn('is_accepted');
        });
    }
};