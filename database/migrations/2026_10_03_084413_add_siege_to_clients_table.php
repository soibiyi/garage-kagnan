<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('siege', 10)->nullable()->index();
        });

        // Rattrapage : un client existant prend le siège de sa première intervention
        DB::table('clients')->get()->each(function ($client) {
            $siege = DB::table('interventions')
                ->join('vehicules', 'vehicules.id', '=', 'interventions.vehicule_id')
                ->where('vehicules.client_id', $client->id)
                ->orderBy('interventions.id')
                ->value('interventions.siege');

            if ($siege) {
                DB::table('clients')->where('id', $client->id)->update(['siege' => $siege]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['siege']);
            $table->dropColumn('siege');
        });
    }
};