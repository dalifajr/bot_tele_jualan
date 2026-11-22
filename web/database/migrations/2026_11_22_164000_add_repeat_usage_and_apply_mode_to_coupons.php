<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (!Schema::hasColumn('coupons', 'allow_multiple_uses')) {
                    $table->boolean('allow_multiple_uses')->default(false)->after('used_qty');
                }
                if (!Schema::hasColumn('coupons', 'max_uses_per_user')) {
                    $table->unsignedInteger('max_uses_per_user')->nullable()->after('allow_multiple_uses');
                }
                if (!Schema::hasColumn('coupons', 'apply_mode')) {
                    $table->string('apply_mode', 20)->default('per_order')->after('max_uses_per_user');
                }
            });
        }

        if (Schema::hasTable('coupon_user')) {
            try {
                Schema::table('coupon_user', function (Blueprint $table) {
                    $table->index(['coupon_id', 'user_id'], 'idx_coupon_user_pair');
                });
            } catch (\Throwable $e) {
                // Index may already exist on migrated databases
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coupon_user')) {
            try {
                Schema::table('coupon_user', function (Blueprint $table) {
                    $table->dropIndex('idx_coupon_user_pair');
                });
            } catch (\Throwable $e) {
            }
        }

        if (Schema::hasTable('coupons')) {
            $cols = ['allow_multiple_uses', 'max_uses_per_user', 'apply_mode'];
            $existing = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('coupons', $c)));
            if (!empty($existing)) {
                Schema::table('coupons', function (Blueprint $table) use ($existing) {
                    $table->dropColumn($existing);
                });
            }
        }
    }
};
