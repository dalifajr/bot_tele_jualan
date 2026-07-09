<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->string('server_ip')->nullable();
            $table->string('protocol')->index(); // ssh, vmess, vless, trojan, shadowsocks
            $table->string('username')->index();
            $table->string('password')->nullable();
            $table->string('uuid')->nullable();
            $table->text('config_link')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->string('status')->default('active'); // active, suspended, expired
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_accounts');
    }
};
