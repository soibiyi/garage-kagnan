<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secrétariat : tâches confiées par l'administrateur à une secrétaire (avec délai),
 * checklist d'étapes par tâche, et messagerie admin ↔ secrétaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretaire_taches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secretaire_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre');
            $table->text('instructions')->nullable();
            $table->dateTime('echeance');
            $table->string('priorite', 20)->default('normale'); // basse, normale, haute
            $table->string('statut', 20)->default('a_faire');   // a_faire, en_cours, terminee
            $table->dateTime('terminee_at')->nullable();
            $table->timestamps();

            $table->index(['secretaire_id', 'statut', 'echeance']);
        });

        Schema::create('secretaire_tache_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tache_id')->constrained('secretaire_taches')->cascadeOnDelete();
            $table->string('libelle');
            $table->boolean('fait')->default(false);
            $table->dateTime('fait_at')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            // L'étape a-t-elle été ajoutée par la secrétaire elle-même ?
            $table->boolean('ajoute_par_secretaire')->default(false);
            $table->timestamps();
        });

        Schema::create('secretaire_messages', function (Blueprint $table) {
            $table->id();
            // Une conversation = une secrétaire (tous les administrateurs y participent)
            $table->foreignId('secretaire_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tache_id')->nullable()->constrained('secretaire_taches')->nullOnDelete();
            $table->text('contenu');
            $table->dateTime('lu_at')->nullable();
            $table->timestamps();

            $table->index(['secretaire_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretaire_messages');
        Schema::dropIfExists('secretaire_tache_items');
        Schema::dropIfExists('secretaire_taches');
    }
};
