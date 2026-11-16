<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos jointes au rapport d'une interaction (suivi des relances du chargé client).
 * Fichiers stockés sur le disque privé, servis via une route autorisée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaction_client_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interaction_client_id')->constrained('interaction_clients')->cascadeOnDelete();
            $table->string('path');
            $table->string('nom')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('taille')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_client_photos');
    }
};
