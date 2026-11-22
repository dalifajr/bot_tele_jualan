<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastRead extends Model
{
    protected $fillable = [
        'broadcast_job_id',
        'user_id',
        'telegram_id',
        'source',
        'via_web',
        'via_telegram',
        'read_at',
    ];

    protected $casts = [
        'via_web' => 'boolean',
        'via_telegram' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function broadcastJob()
    {
        return $this->belongsTo(BroadcastJob::class, 'broadcast_job_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
