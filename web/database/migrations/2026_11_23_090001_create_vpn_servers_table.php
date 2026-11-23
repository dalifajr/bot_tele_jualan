<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('region')->nullable();
            $table->string('driver', 32)->default('vpnxray_api')->index(); // vpnxray_api | legacy_ssh
            $table->string('base_url')->nullable();
            $table->text('api_token')->nullable();
            $table->text('hmac_secret')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(15);
            $table->boolean('verify_ssl')->default(true);
            $table->string('ssh_host')->nullable();
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('ssh_username')->default('root');
            $table->text('ssh_private_key')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamp('last_ping_at')->nullable();
            $table->float('last_ping_ms')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_servers');
    }
};
