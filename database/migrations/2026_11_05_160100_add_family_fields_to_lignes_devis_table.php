<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_devis', function (Blueprint $table) {
            $table->string('famille', 100)->nullable();
            $table->string('sous_famille', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lignes_devis', function (Blueprint $table) {
            $table->dropColumn(['famille', 'sous_famille']);
        });
    }
};
