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

    public function complaints()
    {
        return $this->hasMany(ComplaintCase::class, 'order_id');
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
            'pending_payment' => __('Menunggu Pembayaran'),
            'paid' => __('Dibayar'),
            'delivered' => __('Selesai'),
            'cancelled' => __('Dibatalkan'),
            'expired' => __('Kedaluwarsa'),
            default => __('Unknown')
        };
    }

    public function getWarrantyExpiresAtAttribute()
    {
        if ($this->status !== 'delivered' || !$this->delivered_at) {
            return null;
        }

        $maxDays = 0;
        foreach ($this->items as $item) {
            $days = (int) ($item->product->warranty_days ?? 0);
            if ($days > $maxDays) {
                $maxDays = $days;
            }
        }

        if ($maxDays <= 0) {
            return null;
        }

        return $this->delivered_at->copy()->addDays($maxDays);
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
     * Get warranty expiration datetime for a specific OrderItem.
     */
    public function getWarrantyExpiresAtForItem(OrderItem $item)
    {
        if ($this->status !== 'delivered' || !$this->delivered_at) {
            return null;
        }

        $warrantyDays = (int) ($item->product->warranty_days ?? 0);
        if ($warrantyDays <= 0) {
            return null;
        }

        return $this->delivered_at->copy()->addDays($warrantyDays);
    }

    /**
     * Determine whether warranty is currently active for a specific OrderItem.
     */
    public function isWarrantyActiveForItem(OrderItem $item): bool
    {
        $expiresAt = $this->getWarrantyExpiresAtForItem($item);
        if (!$expiresAt) {
            return false;
        }

        return now()->lessThanOrEqualTo($expiresAt);
    }

    /**
     * Distinct sellers involved in this order, grouped with their respective items.
     *
     * @return \Illuminate\Support\Collection<int, array{seller: \App\Models\User, items: \Illuminate\Support\Collection}>
     */
    public function getDistinctSellers(): \Illuminate\Support\Collection
    {
        $grouped = collect();

        foreach ($this->items as $item) {
            $seller = $item->product?->creator;
            if (!$seller) {
                continue;
            }

            if (!$grouped->has($seller->id)) {
                $grouped->put($seller->id, [
                    'seller' => $seller,
                    'items' => collect(),
                ]);
            }

            $grouped->get($seller->id)['items']->push($item);
        }

        return $grouped->values();
    }

    /**
     * Whether this order spans products from more than one distinct seller.
     */
    public function isMultiSeller(): bool
    {
        return $this->getDistinctSellers()->count() > 1;
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
