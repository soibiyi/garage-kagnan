<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_devis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->constrained('devis')->onDelete('cascade');
            
            $table->string('libelle', 255); // Nom de la pièce ou prestation
            $table->string('type', 50); // piece ou main_d_oeuvre
            $table->integer('quantite')->default(1);
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('total', 10, 2);
            
            // Permet de garder la trace des éléments refusés par le client pour les relances futures
            $table->enum('statut_ligne', ['accepte', 'refuse'])->default('accepte');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_devis');
    }
};