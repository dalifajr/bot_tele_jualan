<?php

namespace App\Models;

use App\Traits\HasSystemTimezone;
use Illuminate\Database\Eloquent\Model;

class WalletTopup extends Model
{
    use HasSystemTimezone;
    protected $table = 'wallet_topups';

    protected $fillable = [
        'user_id',
        'topup_ref',
        'amount',
        'unique_code',
        'total_pay',
        'payment_method',
        'status',
        'verified_source',
        'verified_by',
        'admin_note',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'integer',
        'unique_code' => 'integer',
        'total_pay' => 'integer',
        'verified_by' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
