<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastJob extends Model
{
    protected $fillable = [
        'message',
        'total_targets',
        'sent_count',
        'failed_count',
        'status',
        'admin_id',
        'is_read',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
