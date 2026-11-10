<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->unsignedBigInteger('kilometrage')->nullable();
        });

        DB::table('vehicules')->select('id')->orderBy('id')->chunkById(200, function ($vehicules) {
            foreach ($vehicules as $vehicule) {
                $kilometrage = DB::table('interventions')
                    ->where('vehicule_id', $vehicule->id)
                    ->orderByDesc('date_reception')
                    ->orderByDesc('id')
                    ->value('kilometrage');

                if ($kilometrage !== null) {
                    DB::table('vehicules')
                        ->where('id', $vehicule->id)
                        ->update(['kilometrage' => $kilometrage]);
                }
            }
        });

        Schema::create('vehicule_reparations_externes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->cascadeOnDelete();
            $table->date('date_reparation');
            $table->string('lieu', 255);
            $table->text('description');
            $table->unsignedBigInteger('kilometrage')->nullable();
            $table->decimal('montant', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['vehicule_id', 'date_reparation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicule_reparations_externes');

        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropColumn('kilometrage');
        });
    }
};
