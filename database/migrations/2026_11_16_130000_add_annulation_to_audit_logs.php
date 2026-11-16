<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit :
 *  - lot        : regroupe les traces d'une même requête (ex. suppression d'un dossier = dossier + devis + lignes + facture + paiements),
 *                 pour pouvoir annuler l'opération entière d'un coup ;
 *  - contexte   : client, véhicule et n° OT concernés, figés au moment de l'action (lisibles même après suppression) ;
 *  - annule_le / annule_par : annulation de l'action par le super administrateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('lot')->nullable()->after('id')->index();
            $table->json('contexte')->nullable()->after('libelle');
            $table->timestamp('annule_le')->nullable()->after('ip');
            $table->foreignId('annule_par')->nullable()->after('annule_le')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annule_par');
            $table->dropColumn(['lot', 'contexte', 'annule_le']);
        });
    }
};
