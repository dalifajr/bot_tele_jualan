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
        Schema::table('complaint_cases', function (Blueprint $table) {
            if (!Schema::hasColumn('complaint_cases', 'reopen_count')) {
                $table->integer('reopen_count')->default(0)->after('closed_at');
            }
            if (!Schema::hasColumn('complaint_cases', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('reopen_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complaint_cases', function (Blueprint $table) {
            $table->dropColumn(['reopen_count', 'attachment_path']);
        });
    }
};
