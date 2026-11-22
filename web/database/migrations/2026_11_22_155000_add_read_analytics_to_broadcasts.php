<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('broadcast_jobs')) {
            Schema::table('broadcast_jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('broadcast_jobs', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('broadcast_jobs', 'web_target_count')) {
                    $table->unsignedInteger('web_target_count')->default(0)->after('total_targets');
                }
                if (!Schema::hasColumn('broadcast_jobs', 'read_count')) {
                    $table->unsignedInteger('read_count')->default(0)->after('failed_count');
                }
                if (!Schema::hasColumn('broadcast_jobs', 'web_read_count')) {
                    $table->unsignedInteger('web_read_count')->default(0)->after('read_count');
                }
                if (!Schema::hasColumn('broadcast_jobs', 'telegram_read_count')) {
                    $table->unsignedInteger('telegram_read_count')->default(0)->after('web_read_count');
                }
                if (!Schema::hasColumn('broadcast_jobs', 'read_snapshots')) {
                    $table->json('read_snapshots')->nullable()->after('telegram_read_count');
                }
                if (!Schema::hasColumn('broadcast_jobs', 'last_read_calculated_at')) {
                    $table->timestamp('last_read_calculated_at')->nullable()->after('read_snapshots');
                }
                if (!Schema::hasColumn('broadcast_jobs', 'enable_read_button')) {
                    $table->boolean('enable_read_button')->default(true)->after('is_read');
                }
                if (!Schema::hasColumn('broadcast_jobs', 'cta_label')) {
                    $table->string('cta_label', 80)->nullable()->after('enable_read_button');
                }
                if (!Schema::hasColumn('broadcast_jobs', 'cta_url')) {
                    $table->string('cta_url', 500)->nullable()->after('cta_label');
                }
            });
        }

        if (!Schema::hasTable('broadcast_reads')) {
            Schema::create('broadcast_reads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('broadcast_job_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('telegram_id', 64)->nullable()->index();
                $table->string('source', 32)->default('web_notif');
                $table->boolean('via_web')->default(false);
                $table->boolean('via_telegram')->default(false);
                $table->timestamp('read_at')->useCurrent();
                $table->timestamps();

                $table->unique(['broadcast_job_id', 'user_id'], 'uq_broadcast_reads_job_user');
                $table->index(['broadcast_job_id', 'telegram_id'], 'idx_broadcast_reads_job_tg');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_reads');

        if (Schema::hasTable('broadcast_jobs')) {
            $cols = [
                'completed_at',
                'web_target_count',
                'read_count',
                'web_read_count',
                'telegram_read_count',
                'read_snapshots',
                'last_read_calculated_at',
                'enable_read_button',
                'cta_label',
                'cta_url',
            ];
            $existing = array_values(array_filter($cols, fn ($col) => Schema::hasColumn('broadcast_jobs', $col)));
            if (!empty($existing)) {
                Schema::table('broadcast_jobs', function (Blueprint $table) use ($existing) {
                    $table->dropColumn($existing);
                });
            }
        }
    }
};
