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
        // Drop any partially created tables from previous failed migration attempt
        Schema::dropIfExists('escrow_messages');
        Schema::dropIfExists('escrow_timelines');
        Schema::dropIfExists('escrow_transactions');

        Schema::create('escrow_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 32)->unique();
            $table->unsignedBigInteger('creator_id')->index();
            $table->enum('creator_role', ['buyer', 'seller'])->default('buyer');
            
            // Buyer info
            $table->unsignedBigInteger('buyer_user_id')->nullable()->index();
            $table->string('buyer_name');
            $table->string('buyer_contact');
            
            // Seller info
            $table->unsignedBigInteger('seller_user_id')->nullable()->index();
            $table->string('seller_name');
            $table->string('seller_contact');
            $table->string('seller_bank_name')->nullable();
            $table->string('seller_account_number')->nullable();
            $table->string('seller_account_holder')->nullable();
            
            // Transaction specs
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('fee_amount', 14, 2)->default(0);
            $table->enum('fee_payer', ['buyer', 'seller', 'split'])->default('buyer');
            $table->decimal('total_buyer_pay', 14, 2);
            $table->decimal('total_seller_receive', 14, 2);
            
            // Escrow milestones & proofs
            $table->string('deposit_proof')->nullable();
            $table->dateTime('deposit_verified_at')->nullable();
            $table->unsignedBigInteger('deposit_verified_by')->nullable()->index();
            
            $table->text('delivery_proof')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->unsignedInteger('inspection_hours')->default(24);
            
            $table->string('payout_proof')->nullable();
            $table->dateTime('payout_at')->nullable();
            $table->unsignedBigInteger('payout_by')->nullable()->index();
            
            // Status & tokens
            // Statuses: waiting_deposit, deposit_submitted, funds_held, delivered, waiting_payout, completed, disputed, refunded, cancelled
            $table->string('status', 30)->default('waiting_deposit')->index();
            $table->string('public_token', 64)->unique();
            
            // Dispute & notes
            $table->text('admin_notes')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->unsignedBigInteger('dispute_opened_by')->nullable()->index();
            $table->dateTime('dispute_resolved_at')->nullable();
            $table->string('dispute_resolution')->nullable();
            
            $table->timestamps();
        });

        Schema::create('escrow_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->string('actor_type', 20); // admin, buyer, seller, system
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action', 50);
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('escrow_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->unsignedBigInteger('sender_id')->nullable()->index();
            $table->string('sender_role', 20); // admin, buyer, seller
            $table->string('sender_name');
            $table->text('message');
            $table->string('attachment_path')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_messages');
        Schema::dropIfExists('escrow_timelines');
        Schema::dropIfExists('escrow_transactions');
    }
};
