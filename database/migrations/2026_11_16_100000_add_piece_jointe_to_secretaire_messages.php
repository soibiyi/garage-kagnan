<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pièces jointes (photos et documents) dans la messagerie admin ↔ secrétaire.
 * Le texte devient facultatif : un message peut ne contenir qu'un fichier.
 * Les fichiers sont stockés sur le disque privé et servis via une route autorisée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secretaire_messages', function (Blueprint $table) {
            $table->text('contenu')->nullable()->change();
            $table->string('piece_jointe_path')->nullable()->after('contenu');
            $table->string('piece_jointe_nom')->nullable()->after('piece_jointe_path');
            $table->string('piece_jointe_mime', 150)->nullable()->after('piece_jointe_nom');
            $table->unsignedBigInteger('piece_jointe_taille')->nullable()->after('piece_jointe_mime');
        });
    }

    public function down(): void
    {
        Schema::table('secretaire_messages', function (Blueprint $table) {
            $table->dropColumn(['piece_jointe_path', 'piece_jointe_nom', 'piece_jointe_mime', 'piece_jointe_taille']);
        });

        Schema::table('secretaire_messages', function (Blueprint $table) {
            $table->text('contenu')->nullable(false)->change();
        });
    }
};
