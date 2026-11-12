<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'status')) {
                $table->string('status')->default('actif')->after('type_client');
            }
            if (!Schema::hasColumn('clients', 'banned_at')) {
                $table->timestamp('banned_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['status', 'banned_at']);
        });
    }
};
