<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_vpn')->default(false)->after('is_suspended');
            $table->string('vpn_protocol')->nullable()->after('is_vpn'); // e.g., ssh, vmess, vless, trojan, shadowsocks
            $table->integer('vpn_duration_days')->nullable()->after('vpn_protocol');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_vpn', 'vpn_protocol', 'vpn_duration_days']);
        });
    }
};
