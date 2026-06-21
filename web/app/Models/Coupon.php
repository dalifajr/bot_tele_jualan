<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_spend',
        'max_discount',
        'qty',
        'used_qty',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'integer',
        'min_spend' => 'integer',
        'max_discount' => 'integer',
        'qty' => 'integer',
        'used_qty' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Check if the coupon is valid for a given total and user.
     */
    public function isValidFor($total, $userId = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->qty > 0 && $this->used_qty >= $this->qty) {
            return false;
        }

        if ($total < $this->min_spend) {
            return false;
        }

        if ($userId) {
            // Check if user has already used this coupon
            $used = \Illuminate\Support\Facades\DB::table('coupon_user')
                ->where('coupon_id', $this->id)
                ->where('user_id', $userId)
                ->exists();
            if ($used) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate discount amount.
     */
    public function calculateDiscount($subtotal): int
    {
        if ($this->type === 'percent') {
            $discount = (int)($subtotal * $this->value / 100);
            if ($this->max_discount && $discount > $this->max_discount) {
                return $this->max_discount;
            }
            return $discount;
        }

        // Fixed coupon
        return min($this->value, $subtotal);
    }
}
