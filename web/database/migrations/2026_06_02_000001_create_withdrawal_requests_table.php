<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('bank_name', 100);
            $table->string('account_number', 100);
            $table->string('account_holder', 255);
            $table->string('status', 32)->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->string('proof_image_path', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
