<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_id')->constrained('interventions')->onDelete('cascade');
            $table->foreignId('createur_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->decimal('montant_diagnostic', 10, 2)->default(0.00); // Prestation diagnostic payante isolée
            $table->decimal('montant_total_ht', 10, 2)->default(0.00);
            $table->decimal('montant_total_ttc', 10, 2)->default(0.00);
            $table->string('statut', 50)->default('en_attente'); // ex: en_attente, valide, refuse
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devis');
    }
};