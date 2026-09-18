<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->onDelete('cascade');
            
            // Informations administratives du passage
            $table->string('numero_ot', 191)->nullable();
            $table->date('date_reception')->nullable();
            $table->unsignedBigInteger('kilometrage');
            $table->string('personne_a_contacter', 191)->nullable();

            // Gestion du circuit : 'normal' (avec diagnostic/essais) ou 'devis_direct' (panne visible)
            $table->enum('circuit', ['normal', 'devis_direct'])->default('normal');

            // Traçabilité des rôles
            $table->foreignId('receptionniste_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mecanicien_id')->nullable()->constrained('users')->nullOnDelete();

            // Suivi technique (circuit normal)
            $table->boolean('essai_effectue')->default(false);
            $table->text('rapport_mecanicien')->nullable(); // Confrontation des dires du client vs réalité constatée

            // Photos d'état initial (avant, arrière, gauche, droite)
            $table->string('photo_avant', 255)->nullable();
            $table->string('photo_arriere', 255)->nullable();
            $table->string('photo_gauche', 255)->nullable();
            $table->string('photo_droite', 255)->nullable();

            // Équipements et état à la réception
            $table->boolean('allume_cigare')->default(false);
            $table->boolean('rk7')->default(false);
            $table->boolean('rcd')->default(false);
            $table->boolean('essuie_glace_av')->default(false);
            $table->boolean('essuie_glace_ar')->default(false);
            $table->boolean('retro_ext_gauche')->default(false);
            $table->boolean('retro_ext_droit')->default(false);
            $table->boolean('retro_int')->default(false);
            $table->boolean('cric')->default(false);
            $table->boolean('manivelle')->default(false);
            $table->boolean('roue_secours')->default(false);
            $table->boolean('trousse')->default(false);
            $table->boolean('pare_brise_fissure')->default(false);

            $table->json('enjoliveurs')->nullable();
            $table->string('niveau_carburant', 50)->nullable();
            $table->string('intervalle_niveau_carburant', 191)->nullable();
            $table->text('remarques_eventuelles')->nullable();
            
            $table->string('statut', 50)->default('reception'); // ex: reception, en_essai, diagnostic, devis, en_cours, facture, termine

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};