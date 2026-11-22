<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Coupon extends Model
{
    use HasFactory;

    public const APPLY_PER_ORDER = 'per_order';
    public const APPLY_PER_ITEM = 'per_item';

    protected $fillable = [
        'user_id',
        'code',
        'type',
        'value',
        'min_spend',
        'max_discount',
        'qty',
        'used_qty',
        'allow_multiple_uses',
        'max_uses_per_user',
        'apply_mode',
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
        'allow_multiple_uses' => 'boolean',
        'max_uses_per_user' => 'integer',
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
     * Determine if discount applies per product unit instead of once per order total.
     */
    public function isPerItemMode(): bool
    {
        return ($this->apply_mode ?: self::APPLY_PER_ORDER) === self::APPLY_PER_ITEM;
    }

    /**
     * How many times the given user has already redeemed this coupon.
     */
    public function getUserUsageCount(?int $userId): int
    {
        if (!$userId) {
            return 0;
        }

        return (int) DB::table('coupon_user')
            ->where('coupon_id', $this->id)
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Effective per-account usage cap:
     * - 1 when allow_multiple_uses is OFF
     * - null (unlimited per user) when allow_multiple_uses is ON and max_uses_per_user is null/0
     * - N (> 0) when allow_multiple_uses is ON and max_uses_per_user = N
     */
    public function getEffectiveMaxUsesPerUser(): ?int
    {
        if (!$this->allow_multiple_uses) {
            return 1;
        }

        $limit = (int) ($this->max_uses_per_user ?? 0);
        return $limit > 0 ? $limit : null;
    }

    /**
     * Whether the given user has reached their personal redemption limit for this coupon.
     */
    public function hasReachedUserLimit(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        $maxPerUser = $this->getEffectiveMaxUsesPerUser();
        if ($maxPerUser === null) {
            return false;
        }

        return $this->getUserUsageCount($userId) >= $maxPerUser;
    }

    /**
     * Human-readable error message when user hits personal redemption limit.
     */
    public function getUserLimitErrorMessage(): string
    {
        $maxPerUser = $this->getEffectiveMaxUsesPerUser();
        if ($maxPerUser !== null && $maxPerUser > 1) {
            return __('Anda telah mencapai batas pemakaian kupon ini (maksimal :limit kali per akun).', [
                'limit' => $maxPerUser,
            ]);
        }

        return __('Anda sudah pernah menggunakan kupon ini.');
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

        if ($userId && $this->hasReachedUserLimit((int) $userId)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for a single base amount (total order or single unit).
     */
    public function calculateDiscount($subtotal): int
    {
        $subtotalInt = max(0, (int) $subtotal);

        if ($this->type === 'percent') {
            $percent = max(0, min(100, (int) $this->value));
            $discount = (int) floor(($subtotalInt * $percent) / 100);
            if ($this->max_discount && $discount > $this->max_discount) {
                $discount = (int) $this->max_discount;
            }
            return max(0, min($discount, $subtotalInt));
        }

        // Fixed coupon
        return max(0, min((int) $this->value, $subtotalInt));
    }

    /**
     * Calculate discount for direct checkout of a single product with quantity.
     */
    public function calculateDiscountForUnits(int $unitPrice, int $quantity): int
    {
        $safePrice = max(0, $unitPrice);
        $safeQty = max(1, $quantity);

        if ($this->isPerItemMode()) {
            return $this->calculateDiscount($safePrice) * $safeQty;
        }

        return $this->calculateDiscount($safePrice * $safeQty);
    }

    /**
     * Calculate total discount and per-item discount breakdown for eligible cart items.
     *
     * @param  array<int, object>  $eligibleItems  Each item must have ->id, ->quantity, ->product->price
     * @return array{total_discount: int, item_discounts: array<int, int>}
     */
    public function calculateDiscountForItems(array $eligibleItems): array
    {
        $itemDiscounts = [];
        if (empty($eligibleItems)) {
            return ['total_discount' => 0, 'item_discounts' => []];
        }

        if ($this->isPerItemMode()) {
            $totalDiscount = 0;
            foreach ($eligibleItems as $item) {
                $unitPrice = max(0, (int) ($item->product->price ?? 0));
                $qty = max(1, (int) $item->quantity);
                $lineDiscount = $this->calculateDiscount($unitPrice) * $qty;
                $itemDiscounts[$item->id] = $lineDiscount;
                $totalDiscount += $lineDiscount;
            }

            return [
                'total_discount' => $totalDiscount,
                'item_discounts' => $itemDiscounts,
            ];
        }

        // Mode: per_order (aggregate eligible subtotal, then distribute proportionally)
        $eligibleSubtotal = 0;
        foreach ($eligibleItems as $item) {
            $eligibleSubtotal += max(0, (int) ($item->product->price ?? 0)) * max(1, (int) $item->quantity);
        }

        $totalDiscount = $this->calculateDiscount($eligibleSubtotal);
        $remainingDiscount = $totalDiscount;
        $count = count($eligibleItems);

        foreach (array_values($eligibleItems) as $idx => $item) {
            if ($idx === $count - 1) {
                $itemDiscounts[$item->id] = max(0, $remainingDiscount);
            } else {
                $lineSubtotal = max(0, (int) ($item->product->price ?? 0)) * max(1, (int) $item->quantity);
                $share = $eligibleSubtotal > 0
                    ? (int) round(($lineSubtotal / $eligibleSubtotal) * $totalDiscount)
                    : 0;
                $share = min($share, $remainingDiscount);
                $itemDiscounts[$item->id] = $share;
                $remainingDiscount -= $share;
            }
        }

        return [
            'total_discount' => $totalDiscount,
            'item_discounts' => $itemDiscounts,
        ];
    }
}
