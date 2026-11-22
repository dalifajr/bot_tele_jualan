<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSystemTimezone;

class ComplaintCase extends Model
{
    use HasFactory, HasSystemTimezone;

    protected $table = 'complaint_cases';
    public $timestamps = false;

    protected $fillable = [
        'complaint_ref',
        'customer_id',
        'customer_telegram_id',
        'customer_username_snapshot',
        'order_id',
        'order_item_id',
        'product_id',
        'seller_id',
        'order_ref_snapshot',
        'order_created_at_snapshot',
        'complaint_text',
        'status',
        'rejected_reason',
        'refund_target_detail',
        'refund_requested_at',
        'refund_detail_received_at',
        'refund_proof_file_id',
        'refund_note',
        'refund_transferred_at',
        'refunded_to_wallet_at',
        'closed_at',
        'created_at',
        'updated_at',
        'reopen_count',
        'attachment_path',
    ];

    protected $casts = [
        'order_item_id' => 'integer',
        'product_id' => 'integer',
        'seller_id' => 'integer',
        'order_created_at_snapshot' => 'datetime',
        'refund_requested_at' => 'datetime',
        'refund_detail_received_at' => 'datetime',
        'refund_transferred_at' => 'datetime',
        'refunded_to_wallet_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Resolve the specific OrderItem targeted by this complaint (with legacy fallback).
     */
    public function resolveTargetOrderItem(): ?OrderItem
    {
        if ($this->order_item_id) {
            return $this->orderItem ?: OrderItem::with('product.creator')->find($this->order_item_id);
        }

        if ($this->product_id && $this->order) {
            $match = $this->order->items->firstWhere('product_id', $this->product_id);
            if ($match) {
                return $match;
            }
        }

        return $this->order?->items?->first();
    }

    /**
     * Resolve the specific Product targeted by this complaint (with legacy fallback).
     */
    public function resolveTargetProduct(): ?Product
    {
        if ($this->product_id) {
            return $this->product ?: Product::with('creator')->find($this->product_id);
        }

        return $this->resolveTargetOrderItem()?->product;
    }

    /**
     * Resolve the specific Seller responsible for this complaint (with legacy fallback).
     */
    public function resolveTargetSeller(): ?User
    {
        if ($this->seller_id) {
            return $this->seller ?: User::find($this->seller_id);
        }

        $product = $this->resolveTargetProduct();
        if ($product && $product->creator_id) {
            return $product->creator ?: User::find($product->creator_id);
        }

        return null;
    }

    /**
     * Calculate exact net refundable amount for this complaint:
     * - Item-scoped complaint (multi-item or single-item): net value of that OrderItem (unit_price * qty - discount)
     * - Legacy order-wide complaint without order_item_id: order net paid (total_amount - unique_code)
     */
    public function calculateRefundableAmount(): int
    {
        $order = $this->order;
        if (!$order) {
            return 0;
        }

        $item = $this->order_item_id ? $this->resolveTargetOrderItem() : null;
        if ($item) {
            $itemGross = (int) $item->unit_price * max(1, (int) $item->quantity);
            $itemDiscount = (int) ($item->discount_amount ?? 0);
            if ($itemDiscount <= 0 && (int) ($order->discount_amount ?? 0) > 0 && (int) ($order->subtotal ?? 0) > 0) {
                $itemDiscount = (int) round(($itemGross / (int) $order->subtotal) * (int) $order->discount_amount);
            }
            return max(0, $itemGross - $itemDiscount);
        }

        $orderNet = max(0, (int) $order->total_amount - (int) ($order->unique_code ?? 0));
        return $orderNet > 0 ? $orderNet : (int) $order->total_amount;
    }
}
