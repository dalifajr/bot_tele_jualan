<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastJob extends Model
{
    protected $fillable = [
        'message',
        'media_type',
        'media_path',
        'total_targets',
        'web_target_count',
        'sent_count',
        'failed_count',
        'read_count',
        'web_read_count',
        'telegram_read_count',
        'read_snapshots',
        'last_read_calculated_at',
        'status',
        'completed_at',
        'admin_id',
        'is_read',
        'enable_read_button',
        'cta_label',
        'cta_url',
        'cta_open_mode',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'enable_read_button' => 'boolean',
        'read_snapshots' => 'array',
        'completed_at' => 'datetime',
        'last_read_calculated_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function reads()
    {
        return $this->hasMany(BroadcastRead::class, 'broadcast_job_id');
    }

    /**
     * Effective reach base (avoiding >100% distortion when web + telegram audiences differ).
     */
    public function getReachBaseAttribute(): int
    {
        return max(
            (int) $this->sent_count,
            (int) $this->web_target_count,
            (int) $this->read_count
        );
    }

    /**
     * Confirmed unique read percentage (0 - 100).
     */
    public function getReadRateAttribute(): float
    {
        $base = $this->reach_base;
        if ($base <= 0) {
            return 0.0;
        }

        return round(min(100, ($this->read_count / $base) * 100), 1);
    }
}
