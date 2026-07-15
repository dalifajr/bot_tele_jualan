<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use SoftDeletes, Notifiable;
    protected $table = 'users';

    protected $fillable = [
        'telegram_id',
        'username',
        'full_name',
        'role',
        'registration_ip',
        'email',
        'password',
        'last_seen_at',
        'is_suspended',
        'suspension_reason',
        'wallet_balance',
        'platform_fee_percent',
        'seller_save_hours',
        'allowed_tools',
        'two_factor_enabled',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'is_suspended' => 'boolean',
        'allowed_tools' => 'array',
        'two_factor_enabled' => 'boolean',
        'two_factor_expires_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function isOnline()
    {
        if (!$this->last_seen_at) {
            return false;
        }
        // Online if active in the last 5 minutes
        return $this->last_seen_at->diffInMinutes(now()) < 5;
    }

    public function getLastActiveLabelAttribute()
    {
        if ($this->isOnline()) {
            return 'Online';
        }

        if (!$this->last_seen_at) {
            return 'Offline';
        }

        $lastSeen = $this->last_seen_at;

        if ($lastSeen->isToday()) {
            return 'Aktif hari ini pukul ' . $lastSeen->format('H:i');
        }

        if ($lastSeen->isYesterday()) {
            return 'Aktif kemarin pukul ' . $lastSeen->format('H:i');
        }

        // Return day name and date
        return 'Aktif ' . $lastSeen->translatedFormat('l, d M Y H:i');
    }

    /**
     * The primary key is 'id' (auto-increment, set by Python bot).
     * We don't use password-based auth; auth is via Telegram tokens.
     */
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * Laravel uses 'remember_token' column for remember-me.
     * We need to add this column to the users table if it doesn't exist.
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * Get display name (prefer full_name, fallback to username).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->username ?: 'User #' . $this->telegram_id;
    }
}
