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
            
            // Informations des lignes du devis
            $table->decimal('quantite', 10, 2)->default(1);
            $table->string('designation'); // Anciennement libelle, ou on garde designation
            $table->string('reference_piece')->nullable();
            $table->decimal('pu_net', 15, 2)->default(0);
            $table->decimal('remise', 15, 2)->default(0);
            $table->decimal('montant_ht', 15, 2);
            $table->decimal('montant_ttc', 15, 2);
            $table->boolean('ne_pas_appliquer_tva')->default(false);
            
            // Optionnel : si vous souhaitez garder une notion de type ou de statut de ligne
            $table->string('type', 50)->nullable(); // piece ou main_d_oeuvre
            $table->enum('statut_ligne', ['accepte', 'refuse'])->default('accepte');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_devis');
    }
};