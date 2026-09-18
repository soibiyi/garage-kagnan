<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            
            // Informations d'identification fixes
            $table->string('immatriculation', 50)->unique();
            $table->string('marque', 100)->nullable();
            $table->string('modele', 100)->nullable();
            $table->string('vin', 100)->nullable()->unique();

            // Nouveautés pour les relances (Assurance & Contrôle Technique)
            $table->date('expiration_assurance')->nullable();
            $table->date('expiration_sicta')->nullable(); // Contrôle technique / SICTA
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicules');
    }
};