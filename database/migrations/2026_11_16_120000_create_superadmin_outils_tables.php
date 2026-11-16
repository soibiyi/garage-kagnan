<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outils du super administrateur :
 *  - audit_logs   : historique avant / après des modèles critiques + actions sensibles ;
 *  - connexions   : historique des connexions (réussies et échouées) ;
 *  - users        : suspension d'un compte ;
 *  - parametres   : paramètres globaux modifiables sans toucher au code ;
 *  - annonces     : bandeau d'annonce affiché aux utilisateurs ;
 *  - inventaires  : comptages physiques du stock (écarts d'inventaire) ;
 *  - rapports     : rapports quotidiens / hebdomadaires générés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('impersonator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('auditable_type', 60);
            $table->unsignedBigInteger('auditable_id');
            $table->string('libelle')->nullable(); // ex. « Facture FAC-2026-001 »
            $table->string('evenement', 20); // cree | modifie | supprime
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();
            $table->string('siege', 3)->nullable();
            // Action sensible : motif de l'alerte (null = simple trace)
            $table->string('alerte', 191)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['alerte', 'id']);
            $table->index('created_at');
        });

        Schema::create('connexions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->boolean('reussie');
            $table->string('motif')->nullable(); // échec : mot de passe, compte suspendu…
            $table->boolean('hors_horaires')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
            $table->index(['reussie', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('suspendu_at')->nullable()->after('last_seen_at');
            $table->string('suspendu_motif')->nullable()->after('suspendu_at');
            $table->foreignId('suspendu_par')->nullable()->after('suspendu_motif')->constrained('users')->nullOnDelete();
        });

        Schema::create('parametres', function (Blueprint $table) {
            $table->string('cle', 80)->primary();
            $table->json('valeur')->nullable();
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->text('message')->nullable();
            $table->string('niveau', 10)->default('info'); // info | attention | urgent
            $table->json('roles')->nullable(); // null = tout le monde
            $table->dateTime('debut')->nullable();
            $table->dateTime('fin')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->cascadeOnDelete();
            $table->string('siege', 3);
            $table->integer('quantite_theorique');
            $table->integer('quantite_comptee');
            $table->integer('ecart'); // comptée - théorique
            $table->decimal('valeur_ecart', 14, 2)->default(0); // écart × prix d'achat
            $table->string('commentaire')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['siege', 'created_at']);
        });

        Schema::create('rapports', function (Blueprint $table) {
            $table->id();
            $table->string('periode', 10); // jour | semaine
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->json('donnees');
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete(); // null = automatique
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports');
        Schema::dropIfExists('inventaires');
        Schema::dropIfExists('annonces');
        Schema::dropIfExists('parametres');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspendu_par');
            $table->dropColumn(['suspendu_at', 'suspendu_motif']);
        });
        Schema::dropIfExists('connexions');
        Schema::dropIfExists('audit_logs');
    }
};
