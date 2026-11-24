<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_units')) {
            return;
        }

        Schema::table('stock_units', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_units', 'is_replaced')) {
                $table->boolean('is_replaced')->default(false)->after('stock_status');
            }
            if (!Schema::hasColumn('stock_units', 'replaced_at')) {
                $table->timestamp('replaced_at')->nullable()->after('is_replaced');
            }
            if (!Schema::hasColumn('stock_units', 'replaced_from_stock_id')) {
                $table->unsignedBigInteger('replaced_from_stock_id')->nullable()->after('replaced_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stock_units')) {
            return;
        }

        Schema::table('stock_units', function (Blueprint $table) {
            $drop = [];
            foreach (['replaced_from_stock_id', 'replaced_at', 'is_replaced'] as $col) {
                if (Schema::hasColumn('stock_units', $col)) {
                    $drop[] = $col;
                }
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
