<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramLoginToken extends Model
{
    const UPDATED_AT = null;

    protected $table = 'telegram_login_tokens';

    protected $fillable = [
        'token',
        'link_token',
        'telegram_id',
        'status',
        'ip_address',
        'user_agent',
        'expires_at',
        'link_expires_at',
        'used_at',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'expires_at' => 'datetime',
        'link_expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
