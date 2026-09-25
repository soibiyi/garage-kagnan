<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('marque', 50)->nullable();
            $table->string('modele', 100)->nullable();
            $table->string('generation', 50)->nullable();
            $table->string('moteur', 50)->nullable();
            $table->string('categorie', 100)->nullable();
            $table->string('designation_piece', 200)->nullable();
            $table->string('reference', 50)->nullable();
            $table->string('periodicite', 50)->nullable();
            $table->string('prix_kagnan_ht', 50)->nullable();
            $table->decimal('prix_marche_ht', 12, 2)->nullable();
            $table->decimal('ecart_pourcent', 6, 2)->nullable();
            $table->decimal('prix_ttc_kagnan', 12, 2)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};