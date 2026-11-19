<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'totp_secret')) {
                $table->text('totp_secret')->nullable()->after('two_factor_expires_at');
            }
            if (!Schema::hasColumn('users', 'totp_enabled')) {
                $table->boolean('totp_enabled')->default(false)->after('totp_secret');
            }
            if (!Schema::hasColumn('users', 'totp_recovery_codes')) {
                $table->text('totp_recovery_codes')->nullable()->after('totp_enabled');
            }
            if (!Schema::hasColumn('users', 'totp_confirmed_at')) {
                $table->timestamp('totp_confirmed_at')->nullable()->after('totp_recovery_codes');
            }
        });

        if (!Schema::hasTable('user_sessions')) {
            Schema::create('user_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('session_id', 120)->unique();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->string('device_label', 120)->nullable();
                $table->string('location_label', 150)->nullable();
                $table->timestamp('last_active_at')->nullable()->index();
                $table->timestamp('revoked_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');

        Schema::table('users', function (Blueprint $table) {
            foreach (['totp_confirmed_at', 'totp_recovery_codes', 'totp_enabled', 'totp_secret'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
