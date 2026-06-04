<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GithubCheckResult extends Model
{
    public $timestamps = false;

    protected $table = 'github_check_results';

    protected $fillable = [
        'batch_id',
        'username',
        'result',
        'detail',
        'stock_unit_id',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(GithubCheckBatch::class, 'batch_id');
    }

    public function stockUnit()
    {
        return $this->belongsTo(StockUnit::class, 'stock_unit_id');
    }
}
