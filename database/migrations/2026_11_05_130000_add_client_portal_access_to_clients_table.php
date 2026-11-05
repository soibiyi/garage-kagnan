<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'portal_identifier')) {
                $table->string('portal_identifier', 100)->nullable();
            } else {
                $table->string('portal_identifier', 100)->nullable()->change();
            }
            if (!Schema::hasColumn('clients', 'portal_password')) {
                $table->string('portal_password')->nullable();
            }
            if (!Schema::hasColumn('clients', 'portal_temp_password')) {
                $table->text('portal_temp_password')->nullable();
            }
            if (!Schema::hasColumn('clients', 'portal_password_change_required')) {
                $table->boolean('portal_password_change_required')->default(true);
            }
        });

        if (!Schema::hasIndex('clients', ['portal_identifier'], 'unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unique('portal_identifier');
            });
        }

        DB::table('clients')
            ->where(function ($query) {
                $query->whereNull('portal_identifier')->orWhereNull('portal_password');
            })
            ->orderBy('id')
            ->chunkById(100, function ($clients) {
                foreach ($clients as $client) {
                    $temporaryPassword = Str::random(16);
                    do {
                        $identifier = 'GK-KEY-'.Str::upper(Str::random(6)).'-ID-'.$client->id;
                    } while (DB::table('clients')->where('portal_identifier', $identifier)->exists());

                    DB::table('clients')->where('id', $client->id)->update([
                        'portal_identifier' => $identifier,
                        'portal_password' => Hash::make($temporaryPassword),
                        'portal_temp_password' => Crypt::encryptString($temporaryPassword),
                        'portal_password_change_required' => true,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['portal_identifier']);
            $table->dropColumn([
                'portal_identifier',
                'portal_password',
                'portal_temp_password',
                'portal_password_change_required',
            ]);
        });
    }
};
