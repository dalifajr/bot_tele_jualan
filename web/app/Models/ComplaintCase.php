<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSystemTimezone;

class ComplaintCase extends Model
{
    use HasFactory, HasSystemTimezone;

    protected $table = 'complaint_cases';
    public $timestamps = false; // Using custom datetime logic from sqlalchemy if needed, but Laravel will handle updated_at if true. We'll set false to be safe.

    protected $fillable = [
        'complaint_ref',
        'customer_id',
        'customer_telegram_id',
        'customer_username_snapshot',
        'order_id',
        'order_ref_snapshot',
        'order_created_at_snapshot',
        'complaint_text',
        'status',
        'rejected_reason',
        'refund_target_detail',
        'refund_requested_at',
        'refund_detail_received_at',
        'refund_proof_file_id',
        'refund_note',
        'refund_transferred_at',
        'closed_at',
        'created_at',
        'updated_at',
        'reopen_count',
        'attachment_path'
    ];

    protected $casts = [
        'order_created_at_snapshot' => 'datetime',
        'refund_requested_at' => 'datetime',
        'refund_detail_received_at' => 'datetime',
        'refund_transferred_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
