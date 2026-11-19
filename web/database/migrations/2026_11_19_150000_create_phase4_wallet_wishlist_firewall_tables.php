<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'customer_wallet_balance')) {
                $table->bigInteger('customer_wallet_balance')->default(0)->after('wallet_balance');
            }
        });

        if (Schema::hasTable('complaint_cases')) {
            Schema::table('complaint_cases', function (Blueprint $table) {
                if (!Schema::hasColumn('complaint_cases', 'refunded_to_wallet_at')) {
                    $table->timestamp('refunded_to_wallet_at')->nullable();
                }
            });
        }

        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('type', 16); // credit, debit
                $table->string('category', 32)->index(); // topup, checkout, refund, admin_adjust
                $table->bigInteger('amount');
                $table->bigInteger('balance_before')->default(0);
                $table->bigInteger('balance_after')->default(0);
                $table->string('reference', 80)->nullable()->index();
                $table->string('description', 255)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('wallet_topups')) {
            Schema::create('wallet_topups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('topup_ref', 64)->unique();
                $table->bigInteger('amount');
                $table->integer('unique_code')->default(0);
                $table->bigInteger('total_pay');
                $table->string('payment_method', 32)->default('qris');
                $table->string('status', 24)->default('pending')->index(); // pending, paid, expired, cancelled
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_wishlists')) {
            Schema::create('product_wishlists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->boolean('notify_restock')->default(true);
                $table->timestamp('last_notified_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'product_id']);
            });
        }

        if (!Schema::hasTable('firewall_rules')) {
            Schema::create('firewall_rules', function (Blueprint $table) {
                $table->id();
                $table->string('type', 24)->index(); // ip, subnet, telegram_id
                $table->string('value', 100)->index();
                $table->string('reason', 255)->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedBigInteger('hit_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
        Schema::dropIfExists('product_wishlists');
        Schema::dropIfExists('wallet_topups');
        Schema::dropIfExists('wallet_transactions');

        if (Schema::hasTable('complaint_cases') && Schema::hasColumn('complaint_cases', 'refunded_to_wallet_at')) {
            Schema::table('complaint_cases', function (Blueprint $table) {
                $table->dropColumn('refunded_to_wallet_at');
            });
        }

        if (Schema::hasColumn('users', 'customer_wallet_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('customer_wallet_balance');
            });
        }
    }
};
