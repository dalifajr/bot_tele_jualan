<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reseller_api_keys')) {
            Schema::create('reseller_api_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name', 100);
                $table->string('key_prefix', 32);
                $table->string('key_hash', 64)->unique();
                $table->json('allowed_ips')->nullable();
                $table->string('webhook_url', 500)->nullable();
                $table->string('webhook_secret', 64)->nullable();
                $table->unsignedInteger('rate_limit_per_minute')->default(60);
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->unsignedBigInteger('requests_count')->default(0);
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
                $table->index('revoked_at');
            });
        }

        if (!Schema::hasTable('reseller_api_logs')) {
            Schema::create('reseller_api_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('api_key_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('method', 10);
                $table->string('endpoint', 180);
                $table->unsignedSmallInteger('status_code');
                $table->string('ip_address', 45)->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->string('order_ref', 64)->nullable();
                $table->string('client_ref', 80)->nullable();
                $table->json('request_summary')->nullable();
                $table->string('response_summary', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'created_at']);
                $table->index(['api_key_id', 'created_at']);
                $table->index(['user_id', 'client_ref']);
                $table->index('status_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_api_logs');
        Schema::dropIfExists('reseller_api_keys');
    }
};
