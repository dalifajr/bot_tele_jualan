<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerBankAccount extends Model
{
    protected $table = 'seller_bank_accounts';

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_number',
        'account_holder',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
