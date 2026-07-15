<?php

namespace App\Models;

use App\Traits\HasSystemTimezone;

class WithdrawalRequest extends Model
{
    use HasSystemTimezone;
    protected $table = 'withdrawal_requests';

    const UPDATED_AT = null;

    protected $fillable = [
        'seller_id',
        'amount',
        'bank_name',
        'account_number',
        'account_holder',
        'status',
        'rejection_reason',
        'proof_image_path',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
