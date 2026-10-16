<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mvt_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onNullOnDelete();
            $table->enum('type', ['entree', 'sortie']);
            $table->integer('quantite');
            $table->string('siege')->nullable();
            $table->string('motif')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });

        // Traçabilité rétroactive : Génération des mouvements pour le stock existant
        $stocks = DB::table('stocks')->get();
        foreach ($stocks as $stock) {
            if ($stock->quantite > 0) {
                DB::table('mvt_stocks')->insert([
                    'stock_id' => $stock->id,
                    'user_id' => null,
                    'type' => 'entree',
                    'quantite' => $stock->quantite,
                    'motif' => 'Reprise de stock initial (Rétroactif)',
                    'created_at' => $stock->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            }

            $sieges = DB::table('stock_sieges')->where('stock_id', $stock->id)->get();
            foreach ($sieges as $s) {
                if ($s->sortie > 0) {
                    DB::table('mvt_stocks')->insert([
                        'stock_id' => $stock->id,
                        'user_id' => null,
                        'type' => 'sortie',
                        'quantite' => $s->sortie,
                        'siege' => $s->siege,
                        'motif' => 'Sortie atelier (Rétroactif)',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mvt_stocks');
    }
};