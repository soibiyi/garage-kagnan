<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_portal_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('client_portal_requests', 'chauffeur_id')) {
                $table->unsignedBigInteger('chauffeur_id')->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'chauffeur_accepted_at')) {
                $table->timestamp('chauffeur_accepted_at')->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'mission_started_at')) {
                $table->timestamp('mission_started_at')->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'mission_completed_at')) {
                $table->timestamp('mission_completed_at')->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'client_lat')) {
                $table->decimal('client_lat', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'client_lng')) {
                $table->decimal('client_lng', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'destination_lat')) {
                $table->decimal('destination_lat', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'destination_lng')) {
                $table->decimal('destination_lng', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'gps_consent_at')) {
                $table->timestamp('gps_consent_at')->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'prix_estime')) {
                $table->unsignedInteger('prix_estime')->nullable();
            }
            if (!Schema::hasColumn('client_portal_requests', 'tarif_reference')) {
                $table->string('tarif_reference')->nullable();
            }
        });

        if (
            !Schema::hasIndex('client_portal_requests', 'cpr_chauffeur_fk')
            && !Schema::hasIndex('client_portal_requests', 'client_portal_requests_chauffeur_id_foreign')
        ) {
            Schema::table('client_portal_requests', function (Blueprint $table) {
                $table->foreign('chauffeur_id', 'cpr_chauffeur_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('client_portal_request_rejections')) {
            Schema::create('client_portal_request_rejections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_portal_request_id');
                $table->unsignedBigInteger('chauffeur_id');
                $table->timestamps();
            });
        }

        if (!Schema::hasIndex('client_portal_request_rejections', 'cprr_request_fk')) {
            Schema::table('client_portal_request_rejections', function (Blueprint $table) {
                $table->foreign('client_portal_request_id', 'cprr_request_fk')
                    ->references('id')
                    ->on('client_portal_requests')
                    ->cascadeOnDelete();
            });
        }
        if (!Schema::hasIndex('client_portal_request_rejections', 'cprr_chauffeur_fk')) {
            Schema::table('client_portal_request_rejections', function (Blueprint $table) {
                $table->foreign('chauffeur_id', 'cprr_chauffeur_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }
        if (!Schema::hasIndex('client_portal_request_rejections', 'cprr_request_chauffeur_unique')) {
            Schema::table('client_portal_request_rejections', function (Blueprint $table) {
                $table->unique(
                    ['client_portal_request_id', 'chauffeur_id'],
                    'cprr_request_chauffeur_unique'
                );
            });
        }

        if (!Schema::hasTable('client_portal_request_positions')) {
            Schema::create('client_portal_request_positions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_portal_request_id')->unique(
                    'cprp_request_unique'
                );
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->timestamps();
            });
        }

        if (!Schema::hasIndex('client_portal_request_positions', 'cprp_request_fk')) {
            Schema::table('client_portal_request_positions', function (Blueprint $table) {
                $table->foreign('client_portal_request_id', 'cprp_request_fk')
                    ->references('id')
                    ->on('client_portal_requests')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_request_positions');
        Schema::dropIfExists('client_portal_request_rejections');

        Schema::table('client_portal_requests', function (Blueprint $table) {
            $table->dropForeign('cpr_chauffeur_fk');
            $table->dropColumn([
                'chauffeur_id',
                'chauffeur_accepted_at',
                'mission_started_at',
                'mission_completed_at',
                'client_lat',
                'client_lng',
                'destination_lat',
                'destination_lng',
                'gps_consent_at',
                'prix_estime',
                'tarif_reference',
            ]);
        });
    }
};
