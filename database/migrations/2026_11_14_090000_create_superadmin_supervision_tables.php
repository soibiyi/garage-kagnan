<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super administrateur : un administrateur (role = 'admin') avec is_superadmin = true.
 * Il hérite ainsi de tous les droits admin existants, plus la supervision :
 * journal d'activité en direct des utilisateurs et journal des erreurs de l'application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_superadmin')->default(false)->after('role');
            $table->dateTime('last_seen_at')->nullable()->after('is_superadmin');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            // Superadmin connecté « en tant que » cet utilisateur au moment de l'action
            $table->foreignId('impersonator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 10);
            $table->string('route_name')->nullable();
            $table->string('url', 1000);
            $table->unsignedSmallInteger('status')->nullable();
            $table->json('parametres')->nullable();
            $table->json('donnees')->nullable(); // champs envoyés (mots de passe et jetons exclus)
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('duree_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
            $table->index('created_at');
        });

        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->index();
            $table->string('source', 10)->default('serveur'); // serveur | client
            $table->string('type');
            $table->text('message');
            $table->string('fichier', 500)->nullable();
            $table->unsignedInteger('ligne')->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('method', 10)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('trace')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->dateTime('premiere_fois');
            $table->dateTime('derniere_fois');
            $table->dateTime('resolue_at')->nullable();
            $table->foreignId('resolue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['resolue_at', 'derniere_fois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('activity_logs');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_superadmin', 'last_seen_at']);
        });
    }
};
