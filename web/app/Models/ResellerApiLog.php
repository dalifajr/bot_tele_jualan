<?php

namespace App\Models;

use App\Traits\HasSystemTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerApiLog extends Model
{
    use HasSystemTimezone;

    protected $table = 'reseller_api_logs';

    const UPDATED_AT = null;

    protected $fillable = [
        'api_key_id',
        'user_id',
        'method',
        'endpoint',
        'status_code',
        'ip_address',
        'duration_ms',
        'order_ref',
        'client_ref',
        'request_summary',
        'response_summary',
        'created_at',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'request_summary' => 'array',
        'created_at' => 'datetime',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ResellerApiKey::class, 'api_key_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
