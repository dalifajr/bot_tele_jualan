<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasSystemTimezone;

class Order extends Model
{
    use SoftDeletes, HasSystemTimezone;
    protected $table = 'orders';

    const UPDATED_AT = null;

    protected $fillable = [
        'order_ref',
        'customer_id',
        'subtotal',
        'coupon_code',
        'discount_amount',
        'unique_code',
        'total_amount',
        'status',
        'expires_at',
        'delivered_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'discount_amount' => 'integer',
        'unique_code' => 'integer',
        'total_amount' => 'integer',
        'expires_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'order_id');
    }

    // Accessors for backward compatibility with views
    public function getReferenceAttribute()
    {
        return $this->order_ref;
    }

    public function getTotalPriceAttribute()
    {
        return $this->total_amount;
    }

    public function getQuantityAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function getProductAttribute()
    {
        return $this->items->first()?->product;
    }

    public function stockUnits()
    {
        return $this->hasMany(StockUnit::class, 'sold_order_id');
    }

    public function complaintCase()
    {
        return $this->hasOne(ComplaintCase::class, 'order_id');
    }

    public function vpnAccounts()
    {
        return $this->hasMany(VpnAccount::class);
    }

    public function getUserAttribute()
    {
        return $this->customer;
    }

    /**
     * Get formatted total price in Rupiah.
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    /**
     * Get human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending_payment' => 'Menunggu Pembayaran',
            'paid' => 'Dibayar',
            'delivered' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
            default => 'Unknown'
        };
    }

    public function getWarrantyExpiresAtAttribute()
    {
        if ($this->status !== 'delivered' || !$this->delivered_at) {
            return null;
        }

        $warrantyDays = $this->product->warranty_days ?? 0;
        if ($warrantyDays <= 0) {
            return null; // No warranty
        }

        return $this->delivered_at->copy()->addDays($warrantyDays);
    }

    public function getIsWarrantyActiveAttribute(): bool
    {
        $expiresAt = $this->warranty_expires_at;
        if (!$expiresAt) {
            return false;
        }

        return now()->lessThanOrEqualTo($expiresAt);
    }

    /**
     * Get bootstrap color class for status.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending_payment' => 'warning',
            'paid' => 'info',
            'delivered' => 'success',
            'cancelled' => 'danger',
            'expired' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Generate deterministic HMAC token for direct Telegram Mini App order access.
     */
    public function generateTelegramAccessToken(): string
    {
        $secret = trim((string) (config('services.telegram.bot_token') ?: config('telegram.bot_token') ?: ''));
        if (empty($secret)) {
            $rootEnvPath = base_path('../.env');
            if (file_exists($rootEnvPath)) {
                $envContent = @file_get_contents($rootEnvPath) ?: '';
                if (preg_match('/^BOT_TOKEN=(.+)$/m', $envContent, $matches)) {
                    $secret = trim($matches[1], " \t\n\r\0\x0B\"'");
                }
            }
        }
        if (empty($secret)) {
            $secret = (string) config('app.key');
        }

        return substr(hash_hmac('sha256', "order_access:{$this->id}:{$this->order_ref}", $secret), 0, 24);
    }

    /**
     * Verify deterministic HMAC token for Telegram order detail access.
     */
    public function verifyTelegramAccessToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        return hash_equals($this->generateTelegramAccessToken(), trim((string) $token));
    }
}
