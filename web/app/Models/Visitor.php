<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $fillable = [
        'ip_address',
        'visited_date',
        'location',
        'country',
        'country_code',
        'region',
        'city',
        'latitude',
        'longitude',
        'last_path',
        'hits_count',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'hits_count' => 'integer',
    ];
}
