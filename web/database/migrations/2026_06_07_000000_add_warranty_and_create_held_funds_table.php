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
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'warranty_days')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('warranty_days')->nullable()->default(0)->after('price');
            });
        }

        if (!Schema::hasTable('held_funds')) {
            Schema::create('held_funds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seller_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('product_id');
                $table->integer('amount');
                $table->string('status', 32)->default('held'); // 'held', 'released', 'cancelled'
                $table->timestamp('release_at');
                $table->timestamps();

                $table->index(['seller_id', 'status']);
                $table->index(['status', 'release_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'warranty_days')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('warranty_days');
            });
        }

        Schema::dropIfExists('held_funds');
    }
};
