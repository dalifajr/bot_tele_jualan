<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_accounts', 'uplink_bytes')) {
                $table->unsignedBigInteger('uplink_bytes')->default(0)->after('ip_limit');
            }
            if (!Schema::hasColumn('vpn_accounts', 'downlink_bytes')) {
                $table->unsignedBigInteger('downlink_bytes')->default(0)->after('uplink_bytes');
            }
            if (!Schema::hasColumn('vpn_accounts', 'last_panel_uplink_bytes')) {
                $table->unsignedBigInteger('last_panel_uplink_bytes')->default(0)->after('downlink_bytes');
            }
            if (!Schema::hasColumn('vpn_accounts', 'last_panel_downlink_bytes')) {
                $table->unsignedBigInteger('last_panel_downlink_bytes')->default(0)->after('last_panel_uplink_bytes');
            }
            if (!Schema::hasColumn('vpn_accounts', 'traffic_synced_at')) {
                $table->timestamp('traffic_synced_at')->nullable()->after('last_panel_downlink_bytes');
            }
            if (!Schema::hasColumn('vpn_accounts', 'expiry_notified_stage')) {
                $table->string('expiry_notified_stage', 20)->nullable()->after('expired_at');
            }
            if (!Schema::hasColumn('vpn_accounts', 'expiry_notified_at')) {
                $table->timestamp('expiry_notified_at')->nullable()->after('expiry_notified_stage');
            }
        });

        if (!Schema::hasTable('vpn_traffic_logs')) {
            Schema::create('vpn_traffic_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vpn_account_id');
                $table->date('date');
                $table->unsignedBigInteger('uplink_bytes')->default(0);
                $table->unsignedBigInteger('downlink_bytes')->default(0);
                $table->timestamps();

                $table->unique(['vpn_account_id', 'date']);
                $table->index(['date']);
                $table->foreign('vpn_account_id')->references('id')->on('vpn_accounts')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_traffic_logs');

        Schema::table('vpn_accounts', function (Blueprint $table) {
            foreach ([
                'expiry_notified_at',
                'expiry_notified_stage',
                'traffic_synced_at',
                'last_panel_downlink_bytes',
                'last_panel_uplink_bytes',
                'downlink_bytes',
                'uplink_bytes',
            ] as $col) {
                if (Schema::hasColumn('vpn_accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
