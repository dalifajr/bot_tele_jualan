<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('complaint_cases')) {
            Schema::table('complaint_cases', function (Blueprint $table) {
                if (!Schema::hasColumn('complaint_cases', 'order_item_id')) {
                    $table->unsignedBigInteger('order_item_id')->nullable()->after('order_id')->index();
                }
                if (!Schema::hasColumn('complaint_cases', 'product_id')) {
                    $table->unsignedBigInteger('product_id')->nullable()->after('order_item_id')->index();
                }
                if (!Schema::hasColumn('complaint_cases', 'seller_id')) {
                    $table->unsignedBigInteger('seller_id')->nullable()->after('product_id')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('complaint_cases')) {
            $cols = ['order_item_id', 'product_id', 'seller_id'];
            $existing = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('complaint_cases', $c)));
            if (!empty($existing)) {
                Schema::table('complaint_cases', function (Blueprint $table) use ($existing) {
                    $table->dropColumn($existing);
                });
            }
        }
    }
};
