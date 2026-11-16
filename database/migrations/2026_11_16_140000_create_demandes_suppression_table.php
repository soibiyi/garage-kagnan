<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de suppression : un collaborateur (non administrateur) ne supprime plus lui-même un dossier
 * ou un devis direct, il en fait la demande avec un motif ; un administrateur l'accepte ou la refuse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_suppression', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // dossier | devis_direct
            $table->unsignedBigInteger('cible_id'); // id du dossier (intervention)
            $table->string('libelle')->nullable();
            $table->json('contexte')->nullable(); // client, véhicule, n° OT, montants au moment de la demande
            $table->text('motif');
            $table->string('siege', 3)->nullable();
            $table->foreignId('demandeur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut', 20)->default('en_attente'); // en_attente | approuvee | refusee | caduque
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->string('reponse', 500)->nullable();
            $table->timestamps();

            $table->index(['statut', 'id']);
            $table->index(['type', 'cible_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_suppression');
    }
};
