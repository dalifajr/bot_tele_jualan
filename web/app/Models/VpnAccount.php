<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VpnAccount extends Model
{
    protected $table = 'vpn_accounts';

    protected $fillable = [
        'user_id',
        'order_id',
        'server_ip',
        'protocol',
        'username',
        'password',
        'uuid',
        'config_link',
        'expired_at',
        'status',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
