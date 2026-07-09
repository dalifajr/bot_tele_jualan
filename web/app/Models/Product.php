<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $table = 'products';

    protected $fillable = [
        'name',
        'price',
        'description',
        'is_suspended',
        'creator_id',
        'warranty_days',
        'is_vpn',
        'vpn_protocol',
        'vpn_duration_days',
    ];

    protected $casts = [
        'price' => 'integer',
        'is_suspended' => 'boolean',
        'warranty_days' => 'integer',
        'is_vpn' => 'boolean',
        'vpn_duration_days' => 'integer',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'product_id');
    }

    public function stockUnits()
    {
        return $this->hasMany(StockUnit::class, 'product_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function workers()
    {
        return $this->belongsToMany(User::class, 'product_workers', 'product_id', 'user_id');
    }

    /**
     * Get formatted price in Rupiah.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }
}
