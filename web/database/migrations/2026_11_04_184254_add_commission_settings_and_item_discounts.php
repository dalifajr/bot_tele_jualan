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
        // 1. Add commission_per_product to users table
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'commission_per_product')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('commission_per_product')->default(false)->after('platform_fee_percent');
            });
        }

        // 2. Add platform_fee_percent to products table
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'platform_fee_percent')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('platform_fee_percent')->nullable()->after('price');
            });
        }

        // 3. Add discount_amount to order_items table
        if (Schema::hasTable('order_items') && !Schema::hasColumn('order_items', 'discount_amount')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->integer('discount_amount')->default(0)->after('unit_price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'commission_per_product')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('commission_per_product');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'platform_fee_percent')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('platform_fee_percent');
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'discount_amount')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('discount_amount');
            });
        }
    }
};
