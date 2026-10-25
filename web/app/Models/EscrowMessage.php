<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowMessage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'escrow_transaction_id',
        'sender_id',
        'sender_role',
        'sender_name',
        'message',
        'attachment_path',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(EscrowTransaction::class, 'escrow_transaction_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
