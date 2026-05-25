<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'reference',
        'customer_id',
        'product_id',
        'quantity',
        'total_price',
        'status',
        'expires_at',
        'delivered_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'total_price' => 'integer',
        'expires_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function stockUnits()
    {
        return $this->hasMany(StockUnit::class, 'sold_order_id');
    }

    /**
     * Get formatted total price in Rupiah.
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_price, 0, ',', '.');
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
