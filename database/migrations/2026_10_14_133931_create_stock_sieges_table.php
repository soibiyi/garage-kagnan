<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajout des colonnes de gestion globales sur la table stocks existante
        Schema::table('stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('stocks', 'quantite')) {
                $table->integer('quantite')->default(0)->after('note'); // Quantité initiale immuable
            }
            if (!Schema::hasColumn('stocks', 'prix_achat')) {
                $table->decimal('prix_achat', 12, 2)->nullable()->after('quantite');
            }
            if (!Schema::hasColumn('stocks', 'marge_vente')) {
                $table->decimal('marge_vente', 6, 2)->nullable()->after('prix_achat');
            }
        });

        // Création de la table relationnelle par siège
        Schema::create('stock_sieges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
            $table->string('siege', 50); // 'Terminus 27', 'Zone Industrielle', 'Yamoussoukro'
            $table->integer('quantite_attribuee')->default(0);
            $table->integer('sortie')->default(0);
            $table->timestamps();

            // Une seule ligne par pièce et par siège
            $table->unique(['stock_id', 'siege']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_sieges');

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['quantite', 'prix_achat', 'marge_vente']);
        });
    }
};