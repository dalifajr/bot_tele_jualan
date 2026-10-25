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
        if (!Schema::hasColumn('escrow_transactions', 'unique_code')) {
            Schema::table('escrow_transactions', function (Blueprint $table) {
                $table->unsignedInteger('unique_code')->default(0)->after('fee_payer');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('escrow_transactions', 'unique_code')) {
            Schema::table('escrow_transactions', function (Blueprint $table) {
                $table->dropColumn('unique_code');
            });
        }
    }
};
