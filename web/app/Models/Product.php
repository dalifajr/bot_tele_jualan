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
        'image',
        'is_suspended',
        'creator_id',
        'warranty_days',
        'platform_fee_percent',
        'is_vpn',
        'vpn_protocol',
        'vpn_duration_days',
        'is_vcc',
        'vcc_services',
    ];

    protected $casts = [
        'price' => 'integer',
        'platform_fee_percent' => 'integer',
        'is_suspended' => 'boolean',
        'warranty_days' => 'integer',
        'is_vpn' => 'boolean',
        'vpn_duration_days' => 'integer',
        'is_vcc' => 'boolean',
        'vcc_services' => 'array',
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

    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'coupon_product');
    }

    /**
     * Get image URL from public storage disk if exists.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($this->image, ['http://', 'https://'])) {
            return $this->image;
        }

        return asset('storage/' . $this->image);
    }

    /**
     * Get formatted price in Rupiah.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    /**
     * Get list of VCC services with stock availability.
     */
    public function getVccServicesWithStock(): array
    {
        if (!$this->is_vcc) {
            return [];
        }

        $services = $this->vcc_services;
        if (is_string($services)) {
            $services = json_decode($services, true) ?? [];
        }
        if (!is_array($services)) {
            $services = [];
        }

        $stocks = StockUnit::where('product_id', $this->id)
            ->where('is_sold', false)
            ->whereNull('sold_order_id')
            ->where(function ($query) {
                $query->where('stock_status', 'ready')
                      ->orWhereNull('stock_status');
            })
            ->whereNotNull('vcc_service')
            ->select('vcc_service', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('vcc_service')
            ->pluck('count', 'vcc_service')
            ->toArray();

        $result = [];
        foreach ($services as $service) {
            $count = (int) ($stocks[$service] ?? 0);
            $result[] = [
                'name' => $service,
                'stock' => $count,
                'available' => $count > 0,
            ];
        }

        return $result;
    }
}
