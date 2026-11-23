<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vpn_accounts')) {
            return;
        }

        Schema::table('vpn_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_accounts', 'purchase_price')) {
                $table->unsignedInteger('purchase_price')->nullable()->after('ip_limit');
            }
            if (!Schema::hasColumn('vpn_accounts', 'purchase_days')) {
                $table->unsignedSmallInteger('purchase_days')->nullable()->after('purchase_price');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vpn_accounts')) {
            return;
        }

        Schema::table('vpn_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('vpn_accounts', 'purchase_days')) {
                $table->dropColumn('purchase_days');
            }
            if (Schema::hasColumn('vpn_accounts', 'purchase_price')) {
                $table->dropColumn('purchase_price');
            }
        });
    }
};
