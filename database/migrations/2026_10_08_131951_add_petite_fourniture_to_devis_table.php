<?php

use App\Models\Devis;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Le nom de table est lu depuis le modèle (Devis n'a pas de $table explicite)
    private function table(): string
    {
        return (new Devis)->getTable();
    }

    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            // null = calcul automatique (3 %), sinon montant saisi en FCFA
            $table->unsignedBigInteger('petite_fourniture_montant')->nullable();
            $table->boolean('petite_fourniture_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropColumn(['petite_fourniture_montant', 'petite_fourniture_active']);
        });
    }
};