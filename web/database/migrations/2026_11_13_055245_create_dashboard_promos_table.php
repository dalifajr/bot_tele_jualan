<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashboard_promos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('badge_text')->nullable();
            $table->string('badge_color')->default('#0b57d0');
            $table->text('description')->nullable();
            $table->string('icon')->default('fas fa-tags');
            $table->string('button_text')->default('Lihat Detail');
            $table->string('button_url')->default('/catalog');
            $table->string('gradient_theme')->default('blue'); // blue, green, cyan, purple, amber, red
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed 4 initial default promo cards
        DB::table('dashboard_promos')->insert([
            [
                'title' => 'Diskon Lisensi s/d 30%',
                'badge_text' => 'PROMO SPESIAL',
                'badge_color' => '#0b57d0',
                'description' => 'Gunakan kupon promo spesial saat checkout untuk menikmati potongan harga langsung produk digital.',
                'icon' => 'fas fa-tags',
                'button_text' => 'Klaim promo',
                'button_url' => '/catalog',
                'gradient_theme' => 'blue',
                'order_index' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Lindungi Akun dengan 2FA',
                'badge_text' => 'KEAMANAN AKUN',
                'badge_color' => '#2e7d32',
                'description' => 'Aktifkan Two-Factor Authentication via Google Authenticator untuk keamanan berlapis saat transaksi.',
                'icon' => 'fas fa-shield-alt',
                'button_text' => 'Atur keamanan',
                'button_url' => '/profile',
                'gradient_theme' => 'green',
                'order_index' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Update Cepat via Telegram',
                'badge_text' => 'NOTIFIKASI INSTAN',
                'badge_color' => '#0288d1',
                'description' => 'Dapatkan pembaruan status pengiriman lisensi akun dan bukti transaksi secara otomatis di Telegram.',
                'icon' => 'fab fa-telegram',
                'button_text' => 'Buka Telegram',
                'button_url' => 'https://t.me/' . (config('telegram.bot_username') ?: 'bot'),
                'gradient_theme' => 'cyan',
                'order_index' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Pusat Komplain & Garansi',
                'badge_text' => 'GARANSI RESMI',
                'badge_color' => '#8e44ad',
                'description' => 'Kendala akun atau lisensi? Buka tiket komplain kapan saja untuk penanganan cepat dalam 1x24 jam.',
                'icon' => 'fas fa-headset',
                'button_text' => 'Buka bantuan',
                'button_url' => '/customer/complaints',
                'gradient_theme' => 'purple',
                'order_index' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_promos');
    }
};
