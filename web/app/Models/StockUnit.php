<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSystemTimezone;

class StockUnit extends Model
{
    use HasSystemTimezone;
    protected $table = 'stock_units';
    
    const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'raw_text',
        'is_sold',
        'sold_order_id',
        'stock_status',
        'is_replaced',
        'replaced_at',
        'replaced_from_stock_id',
        'vcc_service',
        'vcc_group_id',
        'available_at',
        'seller_id',
        'uploaded_by_id',
        'github_joined_at',
    ];

    protected $casts = [
        'is_sold' => 'boolean',
        'is_replaced' => 'boolean',
        'replaced_at' => 'datetime',
        'available_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
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

    public function getUmurAkunAttribute()
    {
        $date = $this->github_joined_at ? \Carbon\Carbon::parse($this->github_joined_at) : $this->created_at;
        if (!$date) {
            return '-';
        }
        $diffInMinutes = (int) $date->diffInMinutes(now());

        if ($diffInMinutes < 60) {
            return $diffInMinutes . ' menit yang lalu';
        }

        $diffInHours = (int) $date->diffInHours(now());
        if ($diffInHours < 24) {
            return $diffInHours . ' jam yang lalu';
        }

        $diffInDays = (int) $date->diffInDays(now());
        return $diffInDays . ' hari yang lalu';
    }

    public function getLocalizedUmurAkunAttribute()
    {
        $date = $this->github_joined_at ? \Carbon\Carbon::parse($this->github_joined_at) : $this->created_at;
        if (!$date) {
            return '-';
        }
        $diffInMinutes = (int) $date->diffInMinutes(now());

        if ($diffInMinutes < 60) {
            return $diffInMinutes . ' ' . __('menit yang lalu');
        }

        $diffInHours = (int) $date->diffInHours(now());
        if ($diffInHours < 24) {
            return $diffInHours . ' ' . __('jam yang lalu');
        }

        $diffInDays = (int) $date->diffInDays(now());
        return $diffInDays . ' ' . __('hari yang lalu');
    }
}
