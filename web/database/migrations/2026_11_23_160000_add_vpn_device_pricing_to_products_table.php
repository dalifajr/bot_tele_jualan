<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'vpn_extra_ip_price')) {
                $table->unsignedBigInteger('vpn_extra_ip_price')->default(0)->after('vpn_ip_limit');
            }
            if (!Schema::hasColumn('products', 'vpn_max_ip_limit')) {
                $table->unsignedSmallInteger('vpn_max_ip_limit')->default(10)->after('vpn_extra_ip_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'vpn_max_ip_limit')) {
                $table->dropColumn('vpn_max_ip_limit');
            }
            if (Schema::hasColumn('products', 'vpn_extra_ip_price')) {
                $table->dropColumn('vpn_extra_ip_price');
            }
        });
    }
};
