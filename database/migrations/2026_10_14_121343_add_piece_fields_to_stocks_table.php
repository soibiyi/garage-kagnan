<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('stocks', 'date_entree')) {
                $table->date('date_entree')->nullable();
            }
            if (!Schema::hasColumn('stocks', 'quantite')) {
                $table->integer('quantite')->default(0); // Quantité initiale
            }
            if (!Schema::hasColumn('stocks', 'vehicule_correspondant')) {
                $table->string('vehicule_correspondant')->nullable();
            }
            if (!Schema::hasColumn('stocks', 'prix_achat')) {
                $table->decimal('prix_achat', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('stocks', 'marge_vente')) {
                $table->decimal('marge_vente', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('stocks', 'sortie')) {
                $table->integer('sortie')->default(0); // Utilisation / Consommation
            }
            if (!Schema::hasColumn('stocks', 'attributions_sieges')) {
                $table->json('attributions_sieges')->nullable(); // Stock réparti par siège
            }
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn([
                'date_entree',
                'quantite',
                'vehicule_correspondant',
                'prix_achat',
                'marge_vente',
                'sortie',
                'attributions_sieges',
            ]);
        });
    }
};