<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_accounts', 'vpn_server_id')) {
                $table->unsignedBigInteger('vpn_server_id')->nullable()->after('order_id')->index();
            }
            if (!Schema::hasColumn('vpn_accounts', 'quota_gb')) {
                $table->unsignedInteger('quota_gb')->default(0)->after('uuid');
            }
            if (!Schema::hasColumn('vpn_accounts', 'ip_limit')) {
                $table->unsignedSmallInteger('ip_limit')->default(1)->after('quota_gb');
            }
            if (!Schema::hasColumn('vpn_accounts', 'config_payload')) {
                $table->json('config_payload')->nullable()->after('config_link');
            }
            if (!Schema::hasColumn('vpn_accounts', 'external_ref')) {
                $table->string('external_ref', 128)->nullable()->after('config_payload')->index();
            }
            if (!Schema::hasColumn('vpn_accounts', 'suspended_by_role')) {
                $table->string('suspended_by_role', 20)->nullable()->after('status')->index();
            }
            if (!Schema::hasColumn('vpn_accounts', 'suspended_by_user_id')) {
                $table->unsignedBigInteger('suspended_by_user_id')->nullable()->after('suspended_by_role');
            }
            if (!Schema::hasColumn('vpn_accounts', 'suspended_reason')) {
                $table->text('suspended_reason')->nullable()->after('suspended_by_user_id');
            }
            if (!Schema::hasColumn('vpn_accounts', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('suspended_reason');
            }
            if (!Schema::hasColumn('vpn_accounts', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('suspended_at');
            }
            if (!Schema::hasColumn('vpn_accounts', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            foreach ([
                'last_error',
                'last_synced_at',
                'suspended_at',
                'suspended_reason',
                'suspended_by_user_id',
                'suspended_by_role',
                'external_ref',
                'config_payload',
                'ip_limit',
                'quota_gb',
                'vpn_server_id',
            ] as $col) {
                if (Schema::hasColumn('vpn_accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
