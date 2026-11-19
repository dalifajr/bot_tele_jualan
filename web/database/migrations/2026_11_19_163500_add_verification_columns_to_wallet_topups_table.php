<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_topups')) {
            Schema::table('wallet_topups', function (Blueprint $table) {
                if (!Schema::hasColumn('wallet_topups', 'verified_source')) {
                    $table->string('verified_source', 80)->nullable()->after('status');
                }
                if (!Schema::hasColumn('wallet_topups', 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable()->after('verified_source');
                }
                if (!Schema::hasColumn('wallet_topups', 'admin_note')) {
                    $table->string('admin_note', 500)->nullable()->after('verified_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_topups')) {
            Schema::table('wallet_topups', function (Blueprint $table) {
                foreach (['admin_note', 'verified_by', 'verified_source'] as $col) {
                    if (Schema::hasColumn('wallet_topups', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
