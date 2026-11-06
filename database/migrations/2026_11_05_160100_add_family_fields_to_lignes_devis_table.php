<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_devis', function (Blueprint $table) {
            if (!Schema::hasColumn('lignes_devis', 'famille')) {
                $table->string('famille', 100)->nullable();
            }
            if (!Schema::hasColumn('lignes_devis', 'sous_famille')) {
                $table->string('sous_famille', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lignes_devis', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['famille', 'sous_famille'],
                fn ($c) => Schema::hasColumn('lignes_devis', $c)
            ));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};