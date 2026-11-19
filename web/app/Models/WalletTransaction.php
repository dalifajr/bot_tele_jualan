<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';

    protected $fillable = [
        'user_id',
        'type',
        'category',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'description',
        'meta',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
