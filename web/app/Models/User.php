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
        'registration_ip',
        'email',
        'password',
        'last_seen_at',
        'two_factor_enabled',
        'two_factor_code',
        'two_factor_expires_at',
        'totp_secret',
        'totp_enabled',
        'totp_recovery_codes',
        'totp_confirmed_at',
        'dismiss_set_password_prompt',
        'has_custom_password',
        'avatar',
        'theme_color',
        'commission_per_product',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
        'totp_secret',
        'totp_recovery_codes',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'is_suspended' => 'boolean',
        'allowed_tools' => 'array',
        'two_factor_enabled' => 'boolean',
        'two_factor_expires_at' => 'datetime',
        'totp_secret' => 'encrypted',
        'totp_enabled' => 'boolean',
        'totp_recovery_codes' => 'array',
        'totp_confirmed_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'dismiss_set_password_prompt' => 'boolean',
        'has_custom_password' => 'boolean',
        'commission_per_product' => 'boolean',
    ];

    public function hasAnyTwoFactorEnabled(): bool
    {
        return ($this->totp_enabled && !empty($this->totp_secret))
            || ($this->two_factor_enabled && !empty($this->telegram_id));
    }

    public function userSessions()
    {
        return $this->hasMany(UserSession::class, 'user_id');
    }

    protected static function booted()
    {
        static::updating(function ($user) {
            if ($user->isDirty('role')) {
                $oldRole = $user->getOriginal('role');
                $newRole = $user->role;
                $actorId = \Illuminate\Support\Facades\Auth::id() ?? 0;
                
                try {
                    \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                        'action' => 'user_role_change',
                        'actor_id' => $actorId,
                        'entity_type' => 'user',
                        'entity_id' => $user->id,
                        'detail' => "User role changed: {$user->username} (ID: {$user->id}) from {$oldRole} to {$newRole}",
                        'created_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Ignore audit log write error if audit_logs table isn't ready
                }
            }
        });
    }

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
            return __('Online');
        }

        if (!$this->last_seen_at) {
            return __('Offline');
        }

        $lastSeen = $this->last_seen_at;

        if ($lastSeen->isToday()) {
            return __('Aktif hari ini pukul :time', ['time' => $lastSeen->format('H:i')]);
        }

        if ($lastSeen->isYesterday()) {
            return __('Aktif kemarin pukul :time', ['time' => $lastSeen->format('H:i')]);
        }

        // Return day name and date
        return __('Aktif :date', ['date' => $lastSeen->translatedFormat('l, d M Y H:i')]);
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

    public function savedTwoFactorAccounts()
    {
        return $this->hasMany(SavedTwoFactorAccount::class);
    }

    public function resellerApiKeys()
    {
        return $this->hasMany(ResellerApiKey::class, 'user_id');
    }

    /**
     * Check if user is permitted to use the Reseller H2H REST API.
     */
    public function canUseResellerApi(): bool
    {
        if ($this->is_suspended) {
            return false;
        }

        if ($this->role === 'admin') {
            return true;
        }

        $accessMode = BotSetting::where('key', 'reseller_api_access_mode')->value('value') ?? 'all';
        if ($accessMode === 'all') {
            return true;
        }

        return is_array($this->allowed_tools) && in_array('reseller_api', $this->allowed_tools, true);
    }

    /**
     * Get display name (prefer full_name, fallback to username).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->username ?: 'User #' . $this->telegram_id;
    }

    /**
     * Check if user is allowed to access and use the Rekber (Escrow) feature.
     * Default is TRUE for all users, unless explicitly unchecked by admin in allowed_tools.
     */
    public function canUseRekber(): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        if (is_null($this->allowed_tools)) {
            return true;
        }

        return is_array($this->allowed_tools) && in_array('rekber', $this->allowed_tools);
    }
}
