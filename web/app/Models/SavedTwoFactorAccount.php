<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedTwoFactorAccount extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'secret',
        'issuer',
    ];

    /**
     * Relationship with user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
