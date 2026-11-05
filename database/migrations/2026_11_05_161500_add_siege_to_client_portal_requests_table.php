<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_portal_requests', function (Blueprint $table) {
            $table->string('siege', 10)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('client_portal_requests', function (Blueprint $table) {
            $table->dropIndex(['siege']);
            $table->dropColumn('siege');
        });
    }
};
