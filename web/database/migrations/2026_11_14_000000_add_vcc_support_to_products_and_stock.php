<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add VCC columns to products table
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'is_vcc')) {
                    $table->boolean('is_vcc')->default(false)->after('is_vpn');
                }
                if (!Schema::hasColumn('products', 'vcc_services')) {
                    $table->text('vcc_services')->nullable()->after('is_vcc');
                }
            });
        }

        // 2. Add VCC columns to stock_units table
        if (Schema::hasTable('stock_units')) {
            Schema::table('stock_units', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_units', 'vcc_service')) {
                    $table->string('vcc_service', 100)->nullable()->after('stock_status');
                }
                if (!Schema::hasColumn('stock_units', 'vcc_group_id')) {
                    $table->string('vcc_group_id', 64)->nullable()->after('vcc_service');
                }
            });
        }

        // 3. Add VCC columns to cart_items table
        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                if (!Schema::hasColumn('cart_items', 'vcc_services')) {
                    $table->text('vcc_services')->nullable()->after('quantity');
                }
            });
        }

        // 4. Add VCC columns to order_items table
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('order_items', 'vcc_services')) {
                    $table->text('vcc_services')->nullable()->after('discount_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'vcc_services')) {
                    $table->dropColumn('vcc_services');
                }
                if (Schema::hasColumn('products', 'is_vcc')) {
                    $table->dropColumn('is_vcc');
                }
            });
        }

        if (Schema::hasTable('stock_units')) {
            Schema::table('stock_units', function (Blueprint $table) {
                if (Schema::hasColumn('stock_units', 'vcc_group_id')) {
                    $table->dropColumn('vcc_group_id');
                }
                if (Schema::hasColumn('stock_units', 'vcc_service')) {
                    $table->dropColumn('vcc_service');
                }
            });
        }

        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                if (Schema::hasColumn('cart_items', 'vcc_services')) {
                    $table->dropColumn('vcc_services');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasColumn('order_items', 'vcc_services')) {
                    $table->dropColumn('vcc_services');
                }
            });
        }
    }
};
