<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\QrisService;
use App\Services\TelegramService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Auto-expire pending topups that passed expires_at
        WalletTopup::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $staticQris = BotSetting::where('key', 'qris_static_payload')->value('value');

        $pendingTopups = WalletTopup::where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get()
            ->map(function ($topup) use ($staticQris) {
                $dynamicQris = null;
                if ($staticQris && $topup->total_pay > 0) {
                    try {
                        $dynamicQris = QrisService::buildDynamicPayload($staticQris, (int) $topup->total_pay);
                    } catch (\Throwable $e) {
                        Log::warning("Wallet QRIS generation error for {$topup->topup_ref}: " . $e->getMessage());
                    }
                }
                $topup->dynamic_qris = $dynamicQris;
                return $topup;
            });

        $recentTopups = WalletTopup::where('user_id', $user->id)
            ->where('status', '!=', 'pending')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(15);

        $totalCredits = (int) WalletTransaction::where('user_id', $user->id)
            ->where('type', 'credit')
            ->sum('amount');

        $totalDebits = (int) WalletTransaction::where('user_id', $user->id)
            ->where('type', 'debit')
            ->sum('amount');

        $bankInfo = BotSetting::where('key', 'escrow.bank_info')->value('value')
            ?? BotSetting::where('key', 'payment_bank_info')->value('value');
        $hasQrisConfigured = !empty($staticQris);

        return view('wallet.index', compact(
            'user',
            'pendingTopups',
            'recentTopups',
            'transactions',
            'totalCredits',
            'totalDebits',
            'bankInfo',
            'hasQrisConfigured'
        ));
    }

    public function createTopup(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:10000|max:50000000',
        ]);

        $user = Auth::user();

        // Expire stale pending topups first
        WalletTopup::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $activePending = WalletTopup::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        if ($activePending >= 2) {
            return redirect()->route('wallet.index')
                ->with('error', __('Anda masih memiliki 2 permintaan top-up yang menunggu pembayaran. Selesaikan atau batalkan terlebih dahulu.'));
        }

        $amount = (int) $request->input('amount');

        // Allocate a collision-free unique code across active Orders, Escrows, and WalletTopups
        $busyAmounts = collect()
            ->merge(
                WalletTopup::where('status', 'pending')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->pluck('total_pay')
            )
            ->merge(Order::whereIn('status', ['pending_payment', 'paid'])->pluck('total_amount'))
            ->merge(
                EscrowTransaction::whereIn('status', [
                    EscrowTransaction::STATUS_WAITING_DEPOSIT,
                    EscrowTransaction::STATUS_DEPOSIT_SUBMITTED,
                ])->pluck('total_buyer_pay')
            )
            ->map(fn ($v) => (int) $v)
            ->flip();

        $uniqueCode = random_int(1, 299);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $candidate = random_int(1, 499);
            if (!$busyAmounts->has($amount + $candidate)) {
                $uniqueCode = $candidate;
                break;
            }
        }

        $totalPay = $amount + $uniqueCode;
        $ref = 'TOP' . now()->format('ymdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 4));
        $expiryMinutes = max(15, (int) (BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 30));

        WalletTopup::create([
            'user_id' => $user->id,
            'topup_ref' => $ref,
            'amount' => $amount,
            'unique_code' => $uniqueCode,
            'total_pay' => $totalPay,
            'payment_method' => 'qris',
            'status' => 'pending',
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        return redirect()->route('wallet.index')
            ->with('success', __('Permintaan top-up :ref dibuat. Scan QRIS atau transfer tepat Rp:total untuk verifikasi otomatis.', [
                'ref' => $ref,
                'total' => number_format($totalPay, 0, ',', '.'),
            ]));
    }

    public function topupStatus($id)
    {
        $topup = WalletTopup::where('user_id', Auth::id())->findOrFail($id);

        if ($topup->status === 'pending' && $topup->expires_at && $topup->expires_at->isPast()) {
            $topup->update(['status' => 'expired']);
        }

        return response()->json([
            'id' => $topup->id,
            'topup_ref' => $topup->topup_ref,
            'status' => $topup->status,
            'balance' => (int) (Auth::user()->fresh()->customer_wallet_balance ?? 0),
        ]);
    }

    public function cancelTopup($id)
    {
        $topup = WalletTopup::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->findOrFail($id);

        $topup->update(['status' => 'cancelled']);

        return redirect()->route('wallet.index')
            ->with('success', __('Permintaan top-up :ref dibatalkan.', ['ref' => $topup->topup_ref]));
    }

    public function adminIndex(Request $request)
    {
        // Auto-expire overdue pending topups for accurate status display
        WalletTopup::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $statusFilter = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = WalletTopup::with(['user', 'verifier'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'expired' THEN 1 ELSE 2 END")
            ->orderByDesc('id');

        if (in_array($statusFilter, ['pending', 'paid', 'expired', 'cancelled'], true)) {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('topup_ref', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $topups = $query->paginate(20)->withQueryString();

        $pendingCount = WalletTopup::where('status', 'pending')->count();
        $expiredCount = WalletTopup::where('status', 'expired')->count();
        $totalCustomerBalance = (int) User::sum('customer_wallet_balance');
        $totalPaidTopup = (int) WalletTopup::where('status', 'paid')->sum('amount');

        $customers = User::orderBy('full_name')
            ->orderBy('username')
            ->limit(500)
            ->get(['id', 'username', 'full_name', 'email', 'role', 'customer_wallet_balance']);

        $recentAdjustments = WalletTransaction::with('user')
            ->where('category', 'admin_adjust')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('admin.wallet.index', compact(
            'topups',
            'statusFilter',
            'search',
            'pendingCount',
            'expiredCount',
            'totalCustomerBalance',
            'totalPaidTopup',
            'customers',
            'recentAdjustments'
        ));
    }

    /**
     * Admin konfirmasi manual top-up (khusus permintaan berstatus pending; expired/cancelled ditolak).
     */
    public function adminConfirmTopup(Request $request, $id, WalletService $walletService)
    {
        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        try {
            $note = trim((string) $request->input('admin_note', ''));
            $topup = $walletService->confirmTopup(
                (int) $id,
                Auth::id(),
                'ADMIN_MANUAL',
                $note !== '' ? $note : __('Konfirmasi manual oleh Admin'),
                false
            );

            DB::table('audit_logs')->insert([
                'action' => 'wallet_topup_confirmed_manual',
                'actor_id' => Auth::id(),
                'entity_type' => 'wallet_topup',
                'entity_id' => $topup->id,
                'detail' => "ref={$topup->topup_ref}; user_id={$topup->user_id}; amount={$topup->amount}; note=" . ($note ?: '-'),
                'created_at' => now(),
            ]);

            return redirect()->back()->with('success', __('Top-up :ref sebesar Rp:amount berhasil dikonfirmasi manual dan masuk ke saldo pelanggan.', [
                'ref' => $topup->topup_ref,
                'amount' => number_format($topup->amount, 0, ',', '.'),
            ]));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Admin injeksi / penyesuaian saldo dompet langsung ke user (tambah saldo atau potong saldo).
     */
    public function adminManualCredit(Request $request, WalletService $walletService)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'action_type' => 'nullable|in:credit,debit',
            'amount' => 'required|integer|min:1000|max:50000000',
            'note' => 'required|string|max:255',
            'notify_telegram' => 'nullable|boolean',
        ]);

        $targetUser = User::findOrFail($request->input('user_id'));
        $actionType = (string) $request->input('action_type', 'credit');
        $amount = (int) $request->input('amount');
        $note = trim((string) $request->input('note'));
        $shouldNotify = $request->has('notify_telegram') ? $request->boolean('notify_telegram') : true;

        try {
            if ($actionType === 'debit') {
                $ref = 'ADJ' . now()->format('ymdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 4));
                $tx = $walletService->debit(
                    $targetUser->id,
                    $amount,
                    'admin_adjust',
                    $ref,
                    __('Penyesuaian/Potong saldo Admin: :note', ['note' => $note]),
                    ['actor_id' => Auth::id(), 'manual_adjust' => 'debit']
                );
                $auditAction = 'wallet_balance_deducted';
            } else {
                $ref = 'INJ' . now()->format('ymdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 4));
                $tx = $walletService->credit(
                    $targetUser->id,
                    $amount,
                    'admin_adjust',
                    $ref,
                    __('Injeksi saldo Admin: :note', ['note' => $note]),
                    ['actor_id' => Auth::id(), 'manual_credit' => true, 'manual_adjust' => 'credit']
                );
                $auditAction = 'wallet_manual_credit';
            }
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        DB::table('audit_logs')->insert([
            'action' => $auditAction,
            'actor_id' => Auth::id(),
            'entity_type' => 'wallet_transaction',
            'entity_id' => $tx->id,
            'detail' => "type={$actionType}; ref={$ref}; user_id={$targetUser->id}; amount={$amount}; note={$note}",
            'created_at' => now(),
        ]);

        if ($shouldNotify) {
            try {
                if (!empty($targetUser->telegram_id)) {
                    $targetUser->refresh();
                    $balanceFmt = number_format((int) $targetUser->customer_wallet_balance, 0, ',', '.');
                    $amountFmt = number_format($amount, 0, ',', '.');
                    if ($actionType === 'debit') {
                        TelegramService::sendMessage(
                            $targetUser->telegram_id,
                            "ℹ️ <b>Penyesuaian Saldo Dompet oleh Admin</b>\n\nReferensi: <code>{$ref}</code>\nPotongan: <b>-Rp {$amountFmt}</b>\nCatatan: " . e($note) . "\nSaldo Sekarang: <b>Rp {$balanceFmt}</b>"
                        );
                    } else {
                        TelegramService::sendMessage(
                            $targetUser->telegram_id,
                            "✅ <b>Saldo Dompet Ditambahkan oleh Admin</b>\n\nReferensi: <code>{$ref}</code>\nNominal: <b>+Rp {$amountFmt}</b>\nCatatan: " . e($note) . "\nSaldo Sekarang: <b>Rp {$balanceFmt}</b>"
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Telegram notify error on admin wallet inject/adjust: ' . $e->getMessage());
            }
        }

        $targetLabel = $targetUser->username ?: $targetUser->full_name;
        $successMsg = $actionType === 'debit'
            ? __('Saldo sebesar Rp:amount berhasil dipotong dari akun :user (Saldo akhir: Rp:balance).', [
                'amount' => number_format($amount, 0, ',', '.'),
                'user' => $targetLabel,
                'balance' => number_format((int) $tx->balance_after, 0, ',', '.'),
            ])
            : __('Injeksi saldo Rp:amount berhasil ditambahkan ke akun :user (Saldo akhir: Rp:balance).', [
                'amount' => number_format($amount, 0, ',', '.'),
                'user' => $targetLabel,
                'balance' => number_format((int) $tx->balance_after, 0, ',', '.'),
            ]);

        if ($request->input('redirect_to') === 'back') {
            return redirect()->back()->with('success', $successMsg);
        }

        return redirect()->route('admin.wallet.index')->with('success', $successMsg);
    }

    public function adminRejectTopup($id)
    {
        $topup = WalletTopup::findOrFail($id);
        if ($topup->status !== 'pending') {
            return redirect()->back()->with('error', __('Permintaan top-up ini sudah diproses sebelumnya.'));
        }

        $topup->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', __('Permintaan top-up :ref dibatalkan oleh Admin.', ['ref' => $topup->topup_ref]));
    }
}
