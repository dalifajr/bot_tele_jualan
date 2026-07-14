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
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->index();
            $table->string('username_or_email')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable(); // PC, Smartphone, Tablet, dsb
            $table->string('browser')->nullable();
            $table->string('location')->nullable(); // Negara/Kota
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
    }
};
