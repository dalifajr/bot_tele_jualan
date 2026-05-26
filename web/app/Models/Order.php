<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'order_ref',
        'customer_id',
        'subtotal',
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
        return match ($this->status) {
            'pending_payment' => 'Menunggu Pembayaran',
            'paid' => 'Sudah Dibayar',
            'delivered' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
            default => $this->status,
        };
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
}
