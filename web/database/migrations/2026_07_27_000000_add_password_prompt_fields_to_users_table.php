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
            if (!Schema::hasColumn('users', 'dismiss_set_password_prompt')) {
                $table->boolean('dismiss_set_password_prompt')->default(false)->after('two_factor_expires_at');
            }
            if (!Schema::hasColumn('users', 'has_custom_password')) {
                $table->boolean('has_custom_password')->default(false)->after('dismiss_set_password_prompt');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'dismiss_set_password_prompt')) {
                $table->dropColumn('dismiss_set_password_prompt');
            }
            if (Schema::hasColumn('users', 'has_custom_password')) {
                $table->dropColumn('has_custom_password');
            }
        });
    }
};
