<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockUnit extends Model
{
    protected $table = 'stock_units';
    
    const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'raw_text',
        'is_sold',
        'sold_order_id',
        'stock_status',
        'available_at',
        'seller_id',
        'uploaded_by_id',
    ];

    protected $casts = [
        'is_sold' => 'boolean',
        'available_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'sold_order_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
