<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_check_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->integer('total_accounts')->default(0);
            $table->integer('checked_count')->default(0);
            $table->string('status', 20)->default('running'); // running, completed, stopped
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('github_check_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->string('username');
            $table->string('result', 20); // approved, not_approved, suspended, error
            $table->text('detail')->nullable();
            $table->unsignedBigInteger('stock_unit_id')->nullable();
            $table->timestamp('checked_at')->useCurrent();

            $table->foreign('batch_id')->references('id')->on('github_check_batches')->onDelete('cascade');
            $table->foreign('stock_unit_id')->references('id')->on('stock_units')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_check_results');
        Schema::dropIfExists('github_check_batches');
    }
};
