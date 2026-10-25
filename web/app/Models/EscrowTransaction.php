<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EscrowTransaction extends Model
{
    use HasFactory;

    const STATUS_WAITING_DEPOSIT = 'waiting_deposit';
    const STATUS_DEPOSIT_SUBMITTED = 'deposit_submitted';
    const STATUS_FUNDS_HELD = 'funds_held';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_WAITING_PAYOUT = 'waiting_payout';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DISPUTED = 'disputed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'transaction_code',
        'creator_id',
        'creator_role',
        'buyer_user_id',
        'buyer_name',
        'buyer_contact',
        'seller_user_id',
        'seller_name',
        'seller_contact',
        'seller_bank_name',
        'seller_account_number',
        'seller_account_holder',
        'title',
        'description',
        'amount',
        'fee_amount',
        'fee_payer',
        'total_buyer_pay',
        'total_seller_receive',
        'deposit_proof',
        'deposit_verified_at',
        'deposit_verified_by',
        'delivery_proof',
        'delivered_at',
        'inspection_hours',
        'payout_proof',
        'payout_at',
        'payout_by',
        'status',
        'public_token',
        'admin_notes',
        'dispute_reason',
        'dispute_opened_by',
        'dispute_resolved_at',
        'dispute_resolution',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'total_buyer_pay' => 'decimal:2',
        'total_seller_receive' => 'decimal:2',
        'deposit_verified_at' => 'datetime',
        'delivered_at' => 'datetime',
        'payout_at' => 'datetime',
        'dispute_resolved_at' => 'datetime',
        'inspection_hours' => 'integer',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->transaction_code)) {
                $model->transaction_code = 'RKB-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
            if (empty($model->public_token)) {
                $model->public_token = Str::random(48);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function buyerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function sellerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function depositVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deposit_verified_by');
    }

    public function payoutAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payout_by');
    }

    public function disputeOpener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispute_opened_by');
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(EscrowTimeline::class)->orderBy('created_at', 'asc');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EscrowMessage::class)->orderBy('created_at', 'asc');
    }

    public function logTimeline(string $actorType, ?int $actorId, string $action, string $description): EscrowTimeline
    {
        return $this->timelines()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'description' => $description,
        ]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_WAITING_DEPOSIT => __('Menunggu Pembayaran'),
            self::STATUS_DEPOSIT_SUBMITTED => __('Verifikasi Deposit'),
            self::STATUS_FUNDS_HELD => __('Dana Diamankan (Kirim Barang)'),
            self::STATUS_DELIVERED => __('Pemeriksaan Barang'),
            self::STATUS_WAITING_PAYOUT => __('Menunggu Pencairan'),
            self::STATUS_COMPLETED => __('Selesai'),
            self::STATUS_DISPUTED => __('Dalam Sengketa'),
            self::STATUS_REFUNDED => __('Dikembalikan (Refund)'),
            self::STATUS_CANCELLED => __('Dibatalkan'),
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_WAITING_DEPOSIT => 'warning',
            self::STATUS_DEPOSIT_SUBMITTED => 'info',
            self::STATUS_FUNDS_HELD => 'primary',
            self::STATUS_DELIVERED => 'purple',
            self::STATUS_WAITING_PAYOUT => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_DISPUTED => 'danger',
            self::STATUS_REFUNDED => 'secondary',
            self::STATUS_CANCELLED => 'dark',
            default => 'secondary',
        };
    }

    public function isBuyer(?User $user): bool
    {
        if (!$user) return false;
        if ($this->buyer_user_id === $user->id) return true;
        if ($this->creator_id === $user->id && $this->creator_role === 'buyer') return true;
        return false;
    }

    public function isSeller(?User $user): bool
    {
        if (!$user) return false;
        if ($this->seller_user_id === $user->id) return true;
        if ($this->creator_id === $user->id && $this->creator_role === 'seller') return true;
        return false;
    }

    public function isParticipant(?User $user): bool
    {
        if (!$user) return false;
        if ($user->role === 'admin') return true;
        return $this->isBuyer($user) || $this->isSeller($user);
    }
}
