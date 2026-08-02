<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('login_logs')) {
            Schema::table('login_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('login_logs', 'device_fingerprint')) {
                    $table->string('device_fingerprint', 64)->nullable()->index()->after('browser');
                }
                if (!Schema::hasColumn('login_logs', 'device_id')) {
                    $table->string('device_id', 64)->nullable()->index()->after('device_fingerprint');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('login_logs')) {
            Schema::table('login_logs', function (Blueprint $table) {
                if (Schema::hasColumn('login_logs', 'device_fingerprint')) {
                    $table->dropColumn('device_fingerprint');
                }
                if (Schema::hasColumn('login_logs', 'device_id')) {
                    $table->dropColumn('device_id');
                }
            });
        }
    }
};
