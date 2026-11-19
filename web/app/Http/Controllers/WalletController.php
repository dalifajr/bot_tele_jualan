<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        $pendingTopups = WalletTopup::where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderByDesc('id')
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

        $qrisUrl = BotSetting::where('key', 'qris_image_url')->value('value');
        $bankInfo = BotSetting::where('key', 'escrow.bank_info')->value('value')
            ?? BotSetting::where('key', 'payment_bank_info')->value('value');

        return view('wallet.index', compact(
            'user',
            'pendingTopups',
            'transactions',
            'totalCredits',
            'totalDebits',
            'qrisUrl',
            'bankInfo'
        ));
    }

    public function createTopup(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:10000|max:50000000',
        ]);

        $user = Auth::user();

        $activePending = WalletTopup::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        if ($activePending >= 2) {
            return redirect()->route('wallet.index')
                ->with('error', __('Anda masih memiliki 2 permintaan top-up yang menunggu pembayaran. Selesaikan atau batalkan terlebih dahulu.'));
        }

        $amount = (int) $request->input('amount');
        $uniqueCode = random_int(1, 299);
        $totalPay = $amount + $uniqueCode;
        $ref = 'TOP' . now()->format('ymdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 4));

        WalletTopup::create([
            'user_id' => $user->id,
            'topup_ref' => $ref,
            'amount' => $amount,
            'unique_code' => $uniqueCode,
            'total_pay' => $totalPay,
            'payment_method' => 'qris',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        return redirect()->route('wallet.index')
            ->with('success', __('Permintaan top-up :ref dibuat. Silakan transfer tepat Rp:total.', [
                'ref' => $ref,
                'total' => number_format($totalPay, 0, ',', '.'),
            ]));
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

    public function adminIndex()
    {
        $topups = WalletTopup::with('user')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(20);

        $pendingCount = WalletTopup::where('status', 'pending')->count();
        $totalCustomerBalance = (int) User::sum('customer_wallet_balance');
        $totalPaidTopup = (int) WalletTopup::where('status', 'paid')->sum('amount');

        return view('admin.wallet.index', compact('topups', 'pendingCount', 'totalCustomerBalance', 'totalPaidTopup'));
    }

    public function adminConfirmTopup($id, WalletService $walletService)
    {
        try {
            $topup = $walletService->confirmTopup((int) $id, Auth::id());

            DB::table('audit_logs')->insert([
                'action' => 'wallet_topup_confirmed',
                'actor_id' => Auth::id(),
                'entity_type' => 'wallet_topup',
                'entity_id' => $topup->id,
                'detail' => "ref={$topup->topup_ref}; user_id={$topup->user_id}; amount={$topup->amount}",
                'created_at' => now(),
            ]);

            return redirect()->back()->with('success', __('Top-up :ref sebesar Rp:amount berhasil dikonfirmasi dan masuk ke saldo pelanggan.', [
                'ref' => $topup->topup_ref,
                'amount' => number_format($topup->amount, 0, ',', '.'),
            ]));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
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
