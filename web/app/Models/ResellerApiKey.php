<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ResellerApiKey extends Model
{
    protected $table = 'reseller_api_keys';

    protected $fillable = [
        'user_id',
        'name',
        'key_prefix',
        'key_hash',
        'allowed_ips',
        'webhook_url',
        'webhook_secret',
        'rate_limit_per_minute',
        'is_active',
        'last_used_at',
        'last_used_ip',
        'requests_count',
        'revoked_at',
    ];

    protected $hidden = [
        'key_hash',
        'webhook_secret',
    ];

    protected $casts = [
        'allowed_ips' => 'array',
        'rate_limit_per_minute' => 'integer',
        'is_active' => 'boolean',
        'requests_count' => 'integer',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ResellerApiLog::class, 'api_key_id');
    }

    public static function generateKeyPair(): array
    {
        $rawRandom = bin2hex(random_bytes(24));
        $plainKey = 'rsk_live_' . $rawRandom;
        $prefix = substr($plainKey, 0, 17) . '...';
        $hash = hash('sha256', $plainKey);

        return [
            'plain' => $plainKey,
            'prefix' => $prefix,
            'hash' => $hash,
        ];
    }

    public static function generateWebhookSecret(): string
    {
        return 'whsec_' . bin2hex(random_bytes(20));
    }

    public function isUsable(): bool
    {
        return $this->is_active && is_null($this->revoked_at);
    }

    /**
     * Check whether the given IP is permitted by allowed_ips (exact match or IPv4 CIDR).
     * Empty whitelist permits all IPs.
     */
    public function matchesIp(?string $ip): bool
    {
        $allowed = $this->allowed_ips;
        if (empty($allowed) || !is_array($allowed)) {
            return true;
        }

        if (!$ip) {
            return false;
        }

        foreach ($allowed as $rule) {
            $rule = trim((string) $rule);
            if ($rule === '') {
                continue;
            }
            if ($rule === '*' || $rule === $ip) {
                return true;
            }
            if (str_contains($rule, '/') && self::ipInCidr($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    protected static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Normalize newline/comma separated IP whitelist string into clean array.
     */
    public static function parseIpWhitelist(?string $raw): array
    {
        if (!$raw || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $valid = [];

        foreach ($parts as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (filter_var($item, FILTER_VALIDATE_IP)) {
                $valid[] = $item;
                continue;
            }
            if (str_contains($item, '/')) {
                [$sub, $mask] = array_pad(explode('/', $item, 2), 2, '');
                if (filter_var($sub, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ctype_digit($mask) && (int) $mask >= 8 && (int) $mask <= 32) {
                    $valid[] = $sub . '/' . (int) $mask;
                }
            }
        }

        return array_values(array_unique($valid));
    }
}
