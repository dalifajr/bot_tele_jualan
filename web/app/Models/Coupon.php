<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
        'user_id' => 'integer',
        'value' => 'integer',
        'min_spend' => 'integer',
        'max_discount' => 'integer',
        'qty' => 'integer',
        'used_qty' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user / seller that created this coupon.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Products assigned specifically to this coupon.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    /**
     * Determine if this coupon is restricted to specific products.
     */
    public function isRestrictedToProducts(): bool
    {
        return $this->products()->exists();
    }

    /**
     * Determine if this coupon applies to a given product.
     */
    public function appliesToProduct($product): bool
    {
        $productId = is_object($product) ? $product->id : (int) $product;
        $productModel = is_object($product) ? $product : Product::find($productId);

        if (!$productModel) {
            return false;
        }

        // If coupon was created by a seller, the product must belong to that seller
        if ($this->user_id && $this->creator && $this->creator->role === 'seller') {
            if ($productModel->creator_id != $this->user_id) {
                return false;
            }
        }

        // If coupon has specific products assigned, check if this product is in the list
        if ($this->products()->exists()) {
            return $this->products()->where('products.id', $productId)->exists();
        }

        return true;
    }

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
