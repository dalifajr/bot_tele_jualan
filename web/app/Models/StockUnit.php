<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockUnit extends Model
{
    protected $table = 'stock_units';

    protected $fillable = [
        'product_id',
        'content',
        'is_sold',
        'sold_order_id',
        'stock_status',
    ];

    protected $casts = [
        'is_sold' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'sold_order_id');
    }
}
