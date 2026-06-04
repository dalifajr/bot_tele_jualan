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
        Schema::table('stock_units', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_units', 'github_joined_at')) {
                $table->string('github_joined_at')->nullable()->after('username_key');
            }
        });

        Schema::table('github_check_results', function (Blueprint $table) {
            if (!Schema::hasColumn('github_check_results', 'github_joined_at')) {
                $table->string('github_joined_at')->nullable()->after('detail');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_units', function (Blueprint $table) {
            if (Schema::hasColumn('stock_units', 'github_joined_at')) {
                $table->dropColumn('github_joined_at');
            }
        });

        Schema::table('github_check_results', function (Blueprint $table) {
            if (Schema::hasColumn('github_check_results', 'github_joined_at')) {
                $table->dropColumn('github_joined_at');
            }
        });
    }
};
