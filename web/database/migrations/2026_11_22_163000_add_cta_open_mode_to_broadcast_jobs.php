<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('broadcast_jobs') && !Schema::hasColumn('broadcast_jobs', 'cta_open_mode')) {
            Schema::table('broadcast_jobs', function (Blueprint $table) {
                $table->string('cta_open_mode', 20)->default('miniapp')->after('cta_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('broadcast_jobs') && Schema::hasColumn('broadcast_jobs', 'cta_open_mode')) {
            Schema::table('broadcast_jobs', function (Blueprint $table) {
                $table->dropColumn('cta_open_mode');
            });
        }
    }
};
