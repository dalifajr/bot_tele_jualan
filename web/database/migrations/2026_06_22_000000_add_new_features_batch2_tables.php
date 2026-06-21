<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add snap_token to payments table
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'snap_token')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('snap_token', 255)->nullable()->after('status');
            });
        }

        // 2. Add coupon_code and discount_amount to orders table
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'coupon_code')) {
                    $table->string('coupon_code', 64)->nullable()->after('subtotal');
                }
                if (!Schema::hasColumn('orders', 'discount_amount')) {
                    $table->integer('discount_amount')->default(0)->after('coupon_code');
                }
            });
        }

        // 3. Create coupons table
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('type', 32)->default('fixed'); // fixed, percent
                $table->integer('value');
                $table->integer('min_spend')->default(0);
                $table->integer('max_discount')->nullable();
                $table->integer('qty')->default(0);
                $table->integer('used_qty')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. Create coupon_user table
        if (!Schema::hasTable('coupon_user')) {
            Schema::create('coupon_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coupon_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 5. Create cart_items table
        if (!Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('product_id');
                $table->integer('quantity')->default(1);
                $table->timestamps();
            });
        }

        // 6. Create reviews table
        if (!Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('order_id');
                $table->integer('rating'); // 1-5
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        // 7. Create chat_messages table
        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sender_id');
                $table->unsignedBigInteger('receiver_id');
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('coupon_user');
        Schema::dropIfExists('coupons');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['coupon_code', 'discount_amount']);
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('snap_token');
            });
        }
    }
};
