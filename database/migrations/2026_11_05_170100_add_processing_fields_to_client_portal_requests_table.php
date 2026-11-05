<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_portal_requests', function (Blueprint $table) {
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_portal_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('traite_par');
            $table->dropColumn('traite_le');
        });
    }
};
