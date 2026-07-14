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
        Schema::rename('failed_login_attempts', 'login_logs');
        Schema::table('login_logs', function (Blueprint $table) {
            $table->boolean('is_successful')->default(false)->after('username_or_email');
            $table->text('user_agent')->nullable()->change(); // Just in case it wasn't nullable
        });
    }

    public function down(): void
    {
        Schema::table('login_logs', function (Blueprint $table) {
            $table->dropColumn('is_successful');
        });
        Schema::rename('login_logs', 'failed_login_attempts');
    }
};
