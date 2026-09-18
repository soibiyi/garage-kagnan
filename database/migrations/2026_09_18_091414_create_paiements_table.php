<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures')->onDelete('cascade');
            $table->foreignId('enregistre_par')->nullable()->constrained('users')->nullOnDelete();
            
            $table->decimal('montant', 10, 2);
            $table->string('mode_paiement', 50); // especes, virement, mobile money, etc.
            $table->text('notes')->nullable(); // Échéancier fixe ou versement libre
            $table->timestamp('date_paiement')->useCurrent();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};