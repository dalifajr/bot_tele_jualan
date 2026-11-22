<?php

namespace App\Models;

use App\Traits\HasSystemTimezone;
use Illuminate\Database\Eloquent\Model;

class FirewallRule extends Model
{
    use HasSystemTimezone;
    protected $table = 'firewall_rules';

    protected $fillable = [
        'type',
        'value',
        'reason',
        'expires_at',
        'created_by',
        'is_active',
        'hit_count',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_by' => 'integer',
        'is_active' => 'boolean',
        'hit_count' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeEffective($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public static function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || $bits < 0 || $bits > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
