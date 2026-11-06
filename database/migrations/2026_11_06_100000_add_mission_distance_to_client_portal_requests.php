<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_portal_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('client_portal_requests', 'distance_parcourue_km')) {
                $table->decimal('distance_parcourue_km', 9, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_portal_requests', function (Blueprint $table) {
            $table->dropColumn('distance_parcourue_km');
        });
    }
};