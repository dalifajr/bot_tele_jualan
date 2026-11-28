<?php

namespace App\Models;

use App\Traits\HasSystemTimezone;
use Illuminate\Database\Eloquent\Model;

class VpnAccount extends Model
{
    use HasSystemTimezone;

    public const EXPIRE_GRACE_DAYS = 3;

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
        'uplink_bytes',
        'downlink_bytes',
        'last_panel_uplink_bytes',
        'last_panel_downlink_bytes',
        'traffic_synced_at',
        'purchase_price',
        'purchase_days',
        'config_link',
        'config_payload',
        'external_ref',
        'expired_at',
        'expiry_notified_stage',
        'expiry_notified_at',
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
        'uplink_bytes' => 'integer',
        'downlink_bytes' => 'integer',
        'last_panel_uplink_bytes' => 'integer',
        'last_panel_downlink_bytes' => 'integer',
        'traffic_synced_at' => 'datetime',
        'purchase_price' => 'integer',
        'purchase_days' => 'integer',
        'config_payload' => 'array',
        'expired_at' => 'datetime',
        'expiry_notified_at' => 'datetime',
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

    public function trafficLogs()
    {
        return $this->hasMany(VpnTrafficLog::class, 'vpn_account_id');
    }

    public static function formatBytes(int $bytes): string
    {
        $bytes = max(0, $bytes);
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return ($value >= 100 ? number_format($value, 0) : number_format($value, $value >= 10 ? 1 : 2)) . ' ' . $units[$i];
    }

    /**
     * Expiry visual urgency level & badge styling (normal / warning <= 3d / critical <= 24h / expired).
     *
     * @return array{level: string, hours_left: float|null, pill_class: string, text_class: string, label: string}
     */
    public function getExpiryHighlight(): array
    {
        if (!$this->expired_at) {
            return [
                'level' => 'normal',
                'hours_left' => null,
                'pill_class' => '',
                'text_class' => 'text-muted',
                'label' => '-',
            ];
        }

        $now = now();
        if ($this->isExpiredGraceSuspended()) {
            $remDays = $this->getGraceDaysRemaining();
            return [
                'level' => 'expired_grace',
                'hours_left' => 0.0,
                'pill_class' => 'bg-danger text-white',
                'text_class' => 'text-danger fw-bold',
                'label' => $remDays > 0
                    ? __('Masa Tenggang (Hapus dlm :d hari)', ['d' => $remDays])
                    : __('Masa Tenggang (Hapus Hari Ini)'),
            ];
        }

        if ($this->expired_at->lte($now) || $this->status === 'expired') {
            return [
                'level' => 'expired',
                'hours_left' => 0.0,
                'pill_class' => 'bg-danger text-white',
                'text_class' => 'text-danger fw-bold',
                'label' => __('Sudah Expired'),
            ];
        }

        $hoursLeft = $now->diffInMinutes($this->expired_at) / 60;
        if ($hoursLeft <= 24) {
            $hrs = max(1, (int) ceil($hoursLeft));
            return [
                'level' => 'critical',
                'hours_left' => $hoursLeft,
                'pill_class' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'text_class' => 'text-danger fw-bold',
                'label' => __('Sisa :h jam', ['h' => $hrs]),
            ];
        }

        if ($hoursLeft <= 72) {
            $days = max(1, (int) ceil($hoursLeft / 24));
            return [
                'level' => 'warning',
                'hours_left' => $hoursLeft,
                'pill_class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                'text_class' => 'text-warning-emphasis fw-bold',
                'label' => __('Sisa :d hari', ['d' => $days]),
            ];
        }

        return [
            'level' => 'normal',
            'hours_left' => $hoursLeft,
            'pill_class' => '',
            'text_class' => 'text-muted',
            'label' => $this->expired_at->diffForHumans(),
        ];
    }

    /**
     * Quota remaining progress metadata for quota-limited VPN accounts (quota_gb > 0).
     *
     * @return array{limited: bool, quota_bytes: int, used_bytes: int, remaining_bytes: int, used_percent: float, remaining_percent: float, bar_class: string, text_class: string}
     */
    public function getQuotaUsage(): array
    {
        $quotaGb = max(0, (int) ($this->quota_gb ?? 0));
        $usedBytes = max(0, (int) ($this->uplink_bytes ?? 0)) + max(0, (int) ($this->downlink_bytes ?? 0));

        if ($quotaGb <= 0) {
            return [
                'limited' => false,
                'quota_bytes' => 0,
                'used_bytes' => $usedBytes,
                'remaining_bytes' => 0,
                'used_percent' => 0.0,
                'remaining_percent' => 100.0,
                'bar_class' => 'bg-success',
                'text_class' => 'text-muted',
            ];
        }

        $quotaBytes = $quotaGb * 1073741824;
        $remainingBytes = max(0, $quotaBytes - $usedBytes);
        $usedPct = min(100.0, round(($usedBytes / $quotaBytes) * 100, 1));
        $remPct = max(0.0, round(100.0 - $usedPct, 1));

        $barClass = $remPct < 10.0 ? 'bg-danger' : ($remPct <= 30.0 ? 'bg-warning' : 'bg-success');
        $textClass = $remPct < 10.0 ? 'text-danger fw-semibold' : ($remPct <= 30.0 ? 'text-warning-emphasis fw-semibold' : 'text-muted');

        return [
            'limited' => true,
            'quota_bytes' => $quotaBytes,
            'used_bytes' => $usedBytes,
            'remaining_bytes' => $remainingBytes,
            'used_percent' => $usedPct,
            'remaining_percent' => $remPct,
            'bar_class' => $barClass,
            'text_class' => $textClass,
        ];
    }

    public function isSystemSuspended(): bool
    {
        return $this->status === 'suspended' && $this->suspended_by_role === 'system';
    }

    public function isExpiredGraceSuspended(): bool
    {
        if ($this->status !== 'suspended' || $this->suspended_by_role !== 'system') {
            return false;
        }

        $reasonLower = strtolower((string) ($this->suspended_reason ?? ''));
        if (str_contains($reasonLower, 'kadaluarsa') || str_contains($reasonLower, 'expired') || str_contains($reasonLower, 'masa tenggang')) {
            return true;
        }

        return $this->expired_at !== null && $this->expired_at->lte(now());
    }

    public function getGraceDeleteAt(): ?\Carbon\Carbon
    {
        return $this->expired_at ? $this->expired_at->copy()->addDays(self::EXPIRE_GRACE_DAYS) : null;
    }

    public function getGraceDaysRemaining(): int
    {
        $deleteAt = $this->getGraceDeleteAt();
        if (!$deleteAt || $deleteAt->lte(now())) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInMinutes($deleteAt) / 1440));
    }

    /**
     * Customer can renew normal accounts AND accounts in the 3-day expired grace period (Option B),
     * while accounts locked by Admin or auto-limit IP abuse remain blocked from self-service.
     */
    public function canCustomerRenew(): bool
    {
        if (!$this->isAdminSuspended()) {
            return true;
        }

        return $this->isExpiredGraceSuspended();
    }

    public function isAdminSuspended(): bool
    {
        return $this->status === 'suspended' && in_array($this->suspended_by_role, ['admin', 'system'], true);
    }

    public function isCustomerSuspended(): bool
    {
        return $this->status === 'suspended' && !in_array($this->suspended_by_role, ['admin', 'system'], true);
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
