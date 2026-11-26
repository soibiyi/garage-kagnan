<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->date('date_facture')->nullable()->after('numero_facture');
        });

        DB::table('factures')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('paiements')
                    ->whereColumn('paiements.facture_id', 'factures.id');
            })
            ->update([
                'date_facture' => DB::raw(
                    '(SELECT DATE(MIN(date_paiement)) FROM paiements WHERE paiements.facture_id = factures.id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn('date_facture');
        });
    }
};
