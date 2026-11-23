<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VpnTrafficLog extends Model
{
    protected $table = 'vpn_traffic_logs';

    protected $fillable = [
        'vpn_account_id',
        'date',
        'uplink_bytes',
        'downlink_bytes',
    ];

    protected $casts = [
        'uplink_bytes' => 'integer',
        'downlink_bytes' => 'integer',
    ];

    public function vpnAccount()
    {
        return $this->belongsTo(VpnAccount::class, 'vpn_account_id');
    }
}
