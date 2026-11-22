<?php

namespace App\Models;

use App\Traits\HasSystemTimezone;
use Illuminate\Database\Eloquent\Model;

class BroadcastFailure extends Model
{
    use HasSystemTimezone;

    public const UPDATED_AT = null;

    protected $fillable = [
        'broadcast_job_id',
        'user_id',
        'telegram_id',
        'recipient_name',
        'error_code',
        'error_category',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'error_code' => 'integer',
        'created_at' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(BroadcastJob::class, 'broadcast_job_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Human-readable metadata for each Telegram/network failure category.
     */
    public static function categoryMeta(string $category): array
    {
        return match ($category) {
            'bot_blocked' => [
                'category' => 'bot_blocked',
                'label' => __('Bot Diblokir Pelanggan'),
                'description' => __('Pelanggan memblokir atau menghentikan bot Telegram toko.'),
                'badge_class' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'icon' => 'fas fa-ban',
            ],
            'user_deactivated' => [
                'category' => 'user_deactivated',
                'label' => __('Akun Telegram Dihapus'),
                'description' => __('Akun Telegram pelanggan sudah dihapus atau dinonaktifkan.'),
                'badge_class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                'icon' => 'fas fa-user-slash',
            ],
            'chat_not_found' => [
                'category' => 'chat_not_found',
                'label' => __('Chat Tidak Ditemukan'),
                'description' => __('Pelanggan belum pernah klik /start di bot atau Telegram ID tidak valid.'),
                'badge_class' => 'bg-warning-subtle text-warning border border-warning-subtle',
                'icon' => 'fas fa-comment-slash',
            ],
            'invalid_html' => [
                'category' => 'invalid_html',
                'label' => __('Format Pesan / HTML Error'),
                'description' => __('Tag HTML tidak valid (mis. tag belum ditutup) atau teks/caption melebihi batas Telegram.'),
                'badge_class' => 'bg-info-subtle text-info border border-info-subtle',
                'icon' => 'fas fa-code',
            ],
            'media_error' => [
                'category' => 'media_error',
                'label' => __('Lampiran Media Bermasalah'),
                'description' => __('File media terlalu besar, rusak, atau ditolak oleh Telegram.'),
                'badge_class' => 'bg-primary-subtle text-primary border border-primary-subtle',
                'icon' => 'fas fa-file-excel',
            ],
            'rate_limited' => [
                'category' => 'rate_limited',
                'label' => __('Batas Kecepatan Telegram (429)'),
                'description' => __('Server Telegram membatasi kecepatan kirim sementara meski sudah dicoba ulang.'),
                'badge_class' => 'bg-warning-subtle text-warning border border-warning-subtle',
                'icon' => 'fas fa-gauge-high',
            ],
            'network_error' => [
                'category' => 'network_error',
                'label' => __('Gangguan Jaringan / Timeout'),
                'description' => __('Koneksi ke API Telegram terputus atau melewati batas waktu.'),
                'badge_class' => 'bg-dark-subtle text-body border border-secondary-subtle',
                'icon' => 'fas fa-wifi',
            ],
            default => [
                'category' => 'other',
                'label' => __('Error Lainnya'),
                'description' => __('Error tak terduga dari API Telegram. Periksa pesan teknis untuk detail.'),
                'badge_class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                'icon' => 'fas fa-circle-exclamation',
            ],
        };
    }
}
