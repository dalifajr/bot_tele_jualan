<?php

namespace App\Models;

use App\Traits\HasSystemTimezone;
use Illuminate\Database\Eloquent\Model;

class VpnServer extends Model
{
    use HasSystemTimezone;

    public const DRIVER_VPNXRAY_API = 'vpnxray_api';
    public const DRIVER_LEGACY_SSH = 'legacy_ssh';

    protected $table = 'vpn_servers';

    protected $fillable = [
        'name',
        'code',
        'region',
        'driver',
        'base_url',
        'api_token',
        'hmac_secret',
        'timeout_seconds',
        'verify_ssl',
        'ssh_host',
        'ssh_port',
        'ssh_username',
        'ssh_private_key',
        'is_active',
        'is_default',
        'last_ping_at',
        'last_ping_ms',
        'last_status',
        'last_error',
    ];

    protected $hidden = [
        'api_token',
        'hmac_secret',
        'ssh_private_key',
    ];

    protected $casts = [
        'api_token' => 'encrypted',
        'hmac_secret' => 'encrypted',
        'ssh_private_key' => 'encrypted',
        'timeout_seconds' => 'integer',
        'verify_ssl' => 'boolean',
        'ssh_port' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_ping_at' => 'datetime',
        'last_ping_ms' => 'float',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'vpn_server_id');
    }

    public function vpnAccounts()
    {
        return $this->hasMany(VpnAccount::class, 'vpn_server_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isApiDriver(): bool
    {
        return $this->driver === self::DRIVER_VPNXRAY_API;
    }

    public function isConfigured(): bool
    {
        if ($this->isApiDriver()) {
            return !empty($this->base_url) && !empty($this->api_token) && !empty($this->hmac_secret);
        }

        return !empty($this->ssh_host) && !empty($this->ssh_private_key);
    }

    public function getDisplayEndpointAttribute(): string
    {
        if ($this->isApiDriver()) {
            return (string) ($this->base_url ?: '-');
        }

        return trim((string) $this->ssh_host) . ':' . ($this->ssh_port ?: 22);
    }
}
