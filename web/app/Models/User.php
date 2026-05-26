<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'telegram_id',
        'username',
        'full_name',
        'role',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
    ];

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
