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
        if (!Schema::hasTable('complaint_cases')) {
            Schema::create('complaint_cases', function (Blueprint $table) {
                $table->id();
                $table->string('complaint_ref', 64)->unique();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('customer_telegram_id')->index();
                $table->string('customer_username_snapshot', 255)->nullable();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('order_ref_snapshot', 64)->index();
                $table->timestamp('order_created_at_snapshot')->nullable();
                $table->text('complaint_text')->nullable();
                $table->string('status', 64)->default('new')->index();
                $table->text('rejected_reason')->nullable();
                $table->text('refund_target_detail')->nullable();
                $table->timestamp('refund_requested_at')->nullable();
                $table->timestamp('refund_detail_received_at')->nullable();
                $table->string('refund_proof_file_id', 255)->nullable();
                $table->text('refund_note')->nullable();
                $table->timestamp('refund_transferred_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('complaint_attachments')) {
            Schema::create('complaint_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('complaint_id')->index();
                $table->string('file_id', 255);
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop since it's managed by Python
    }
};
