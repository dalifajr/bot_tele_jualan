<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'wallet_balance')) {
                $table->integer('wallet_balance')->default(0)->after('role');
            }
            if (!Schema::hasColumn('users', 'platform_fee_percent')) {
                $table->integer('platform_fee_percent')->default(10)->after('wallet_balance');
            }
            if (!Schema::hasColumn('users', 'seller_save_hours')) {
                $table->integer('seller_save_hours')->default(80)->after('platform_fee_percent');
            }
        });

        Schema::table('stock_units', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_units', 'seller_id')) {
                $table->foreignId('seller_id')->nullable()->after('product_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_balance', 'platform_fee_percent', 'seller_save_hours']);
        });

        Schema::table('stock_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
        });
    }
};
