<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_portal_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('client_portal_requests', 'garage_lat')) {
                $table->decimal('garage_lat', 10, 7)->nullable()->after('destination_lat');
            }
            if (!Schema::hasColumn('client_portal_requests', 'garage_lng')) {
                $table->decimal('garage_lng', 10, 7)->nullable()->after('garage_lat');
            }
            if (!Schema::hasColumn('client_portal_requests', 'recovery_confirmed_at')) {
                $table->timestamp('recovery_confirmed_at')->nullable()->after('mission_started_at');
            }
            if (!Schema::hasColumn('client_portal_requests', 'garage_arrival_at')) {
                $table->timestamp('garage_arrival_at')->nullable()->after('recovery_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_portal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'garage_lat',
                'garage_lng',
                'recovery_confirmed_at',
                'garage_arrival_at',
            ]);
        });
    }
};
