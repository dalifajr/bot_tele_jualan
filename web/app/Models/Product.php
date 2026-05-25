<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'name',
        'price',
        'description',
        'is_suspended',
    ];

    protected $casts = [
        'price' => 'integer',
        'is_suspended' => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'product_id');
    }

    public function stockUnits()
    {
        return $this->hasMany(StockUnit::class, 'product_id');
    }

    /**
     * Get formatted price in Rupiah.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }
}
