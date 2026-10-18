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
        // 1. Add user_id to coupons table
        if (Schema::hasTable('coupons') && !Schema::hasColumn('coupons', 'user_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            });
        }

        // 2. Create coupon_product pivot table
        if (!Schema::hasTable('coupon_product')) {
            Schema::create('coupon_product', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coupon_id');
                $table->unsignedBigInteger('product_id');
                $table->timestamps();

                $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                $table->unique(['coupon_id', 'product_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('coupon_product')) {
            Schema::dropIfExists('coupon_product');
        }

        if (Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'user_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
};
