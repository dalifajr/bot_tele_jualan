<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $table = 'login_logs';
    protected $fillable = [
        'ip_address',
        'username_or_email',
        'is_successful',
        'user_agent',
        'device_type',
        'browser',
        'location',
    ];
}
