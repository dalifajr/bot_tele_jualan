<?php

namespace App\Models;

use App\Traits\HasSystemTimezone;
use Illuminate\Database\Eloquent\Model;

class VpnAccount extends Model
{
    use HasSystemTimezone;

    protected $table = 'vpn_accounts';

    protected $fillable = [
        'user_id',
        'order_id',
        'vpn_server_id',
        'server_ip',
        'protocol',
        'username',
        'password',
        'uuid',
        'quota_gb',
        'ip_limit',
        'config_link',
        'config_payload',
        'external_ref',
        'expired_at',
        'status',
        'suspended_by_role',
        'suspended_by_user_id',
        'suspended_reason',
        'suspended_at',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'quota_gb' => 'integer',
        'ip_limit' => 'integer',
        'config_payload' => 'array',
        'expired_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function vpnServer()
    {
        return $this->belongsTo(VpnServer::class, 'vpn_server_id');
    }

    public function suspendedBy()
    {
        return $this->belongsTo(User::class, 'suspended_by_user_id');
    }

    public function isAdminSuspended(): bool
    {
        return $this->status === 'suspended' && $this->suspended_by_role === 'admin';
    }

    public function isCustomerSuspended(): bool
    {
        return $this->status === 'suspended' && $this->suspended_by_role !== 'admin';
    }

    public function getPrimaryLinkAttribute(): ?string
    {
        $links = $this->config_payload['links'] ?? [];
        if (is_array($links)) {
            return $links['tls'] ?? $links['ntls'] ?? $links['grpc'] ?? null;
        }
        return null;
    }

    public function getDisplayDomainAttribute(): string
    {
        return (string) ($this->config_payload['domain'] ?? $this->server_ip ?? $this->vpnServer?->name ?? '-');
    }
}
