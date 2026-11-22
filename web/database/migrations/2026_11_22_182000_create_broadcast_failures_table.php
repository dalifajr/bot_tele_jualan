<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('broadcast_failures')) {
            Schema::create('broadcast_failures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('broadcast_job_id')
                    ->constrained('broadcast_jobs')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('telegram_id', 64)->nullable();
                $table->string('recipient_name', 160)->nullable();
                $table->integer('error_code')->nullable();
                $table->string('error_category', 40)->default('other');
                $table->text('error_message');
                $table->timestamp('created_at')->nullable();

                $table->index(['broadcast_job_id', 'error_category'], 'idx_bc_failures_job_cat');
                $table->index(['broadcast_job_id', 'id'], 'idx_bc_failures_job_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_failures');
    }
};
