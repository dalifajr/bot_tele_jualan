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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable()->change();
            }
            if (!Schema::hasColumn('users', 'telegram_id')) {
                $table->unsignedBigInteger('telegram_id')->unique()->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('telegram_id');
            }
            if (!Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name')->nullable()->after('username');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 32)->default('customer')->after('full_name');
            }
            if (!Schema::hasColumn('users', 'wallet_balance')) {
                $table->integer('wallet_balance')->default(0)->after('role');
            }
            if (!Schema::hasColumn('users', 'platform_fee_percent')) {
                $table->integer('platform_fee_percent')->default(10)->after('wallet_balance');
            }
            if (!Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('updated_at');
            }
        });

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->integer('price');
                $table->boolean('is_suspended')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_ref', 64)->unique();
                $table->unsignedBigInteger('customer_id');
                $table->string('status', 32)->default('pending_payment');
                $table->integer('subtotal');
                $table->integer('unique_code');
                $table->integer('total_amount');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->unsignedBigInteger('checkout_chat_id')->nullable();
                $table->unsignedBigInteger('checkout_message_id')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
                $table->unsignedBigInteger('admin_notify_chat_id')->nullable();
                $table->unsignedBigInteger('admin_notify_message_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('product_id');
                $table->integer('quantity');
                $table->integer('unit_price');
                
                $table->unique(['order_id', 'product_id'], 'uq_order_item_product');
            });
        }

        if (!Schema::hasTable('stock_units')) {
            Schema::create('stock_units', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->text('raw_text');
                $table->text('parsed_json')->nullable();
                $table->string('stock_status', 32)->default('ready');
                $table->timestamp('available_at')->nullable();
                $table->string('username_key', 255)->nullable();
                $table->boolean('is_sold')->default(false);
                $table->unsignedBigInteger('sold_order_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('telegram_login_tokens')) {
            Schema::create('telegram_login_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('token', 128)->unique();
                $table->string('link_token', 128)->unique()->nullable();
                $table->unsignedBigInteger('telegram_id')->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('link_expires_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bot_settings')) {
            Schema::create('bot_settings', function (Blueprint $table) {
                $table->string('key', 64)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We do not drop these tables because they are owned by the external system/bot
    }
};
