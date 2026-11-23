<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'vpn_server_id')) {
                $table->unsignedBigInteger('vpn_server_id')->nullable()->after('vpn_duration_days')->index();
            }
            if (!Schema::hasColumn('products', 'vpn_quota_gb')) {
                $table->unsignedInteger('vpn_quota_gb')->default(0)->after('vpn_server_id');
            }
            if (!Schema::hasColumn('products', 'vpn_ip_limit')) {
                $table->unsignedSmallInteger('vpn_ip_limit')->default(1)->after('vpn_quota_gb');
            }
            if (!Schema::hasColumn('products', 'vpn_sni_mode')) {
                $table->string('vpn_sni_mode', 4)->default('3')->after('vpn_ip_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['vpn_sni_mode', 'vpn_ip_limit', 'vpn_quota_gb', 'vpn_server_id'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
