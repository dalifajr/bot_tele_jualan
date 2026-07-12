<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeldFund extends Model
{
    protected $table = 'held_funds';

    protected $fillable = [
        'seller_id',
        'order_id',
        'product_id',
        'amount',
        'status',
        'release_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'release_at' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }
}
