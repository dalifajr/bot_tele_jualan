<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSystemTimezone;

class LoginLog extends Model
{
    use HasSystemTimezone;
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
