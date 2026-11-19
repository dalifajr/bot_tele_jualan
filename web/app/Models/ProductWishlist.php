<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductWishlist extends Model
{
    protected $table = 'product_wishlists';

    protected $fillable = [
        'user_id',
        'product_id',
        'notify_restock',
        'last_notified_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'product_id' => 'integer',
        'notify_restock' => 'boolean',
        'last_notified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
