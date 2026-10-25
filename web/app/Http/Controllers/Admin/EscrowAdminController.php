<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotSetting;
use App\Models\EscrowTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EscrowAdminController extends Controller
{
    /**
     * Dashboard & Daftar Seluruh Transaksi Rekber.
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $query = EscrowTransaction::with(['creator', 'buyerUser', 'sellerUser']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('buyer_name', 'like', "%{$search}%")
                  ->orWhere('seller_name', 'like', "%{$search}%");
            });
        }

        $transactions = $query->orderBy('updated_at', 'desc')->paginate(15);

        // Metrik Ringkasan
        $holdingFunds = EscrowTransaction::whereIn('status', [
            EscrowTransaction::STATUS_FUNDS_HELD,
            EscrowTransaction::STATUS_DELIVERED,
            EscrowTransaction::STATUS_WAITING_PAYOUT,
            EscrowTransaction::STATUS_DISPUTED,
        ])->sum('amount');

        $pendingDepositCount = EscrowTransaction::where('status', EscrowTransaction::STATUS_DEPOSIT_SUBMITTED)->count();
        $pendingPayoutCount = EscrowTransaction::where('status', EscrowTransaction::STATUS_WAITING_PAYOUT)->count();
        $disputedCount = EscrowTransaction::where('status', EscrowTransaction::STATUS_DISPUTED)->count();
        $completedTotalFee = EscrowTransaction::where('status', EscrowTransaction::STATUS_COMPLETED)->sum('fee_amount');

        // Pengaturan Rekber (Persentase Fee & Rekening Toko)
        $feePercent = (float)(BotSetting::where('key', 'escrow.fee_percent')->value('value') ?? 1.5);
        $bankInfo = BotSetting::where('key', 'escrow.bank_info')->value('value') ?? "Bank BCA: 1234567890\na.n Dzulfikrialifajri Store";

        return view('admin.escrow.index', compact(
            'transactions',
            'status',
            'search',
            'holdingFunds',
            'pendingDepositCount',
            'pendingPayoutCount',
            'disputedCount',
            'completedTotalFee',
            'feePercent',
            'bankInfo'
        ));
    }

    /**
     * Detail Transaksi Rekber dari Sudut Pandang Admin.
     */
    public function show(string $code)
    {
        $transaction = EscrowTransaction::with(['timelines.actor', 'messages.sender', 'creator'])
            ->where('transaction_code', $code)
            ->firstOrFail();

        return view('admin.escrow.show', compact('transaction'));
    }

    /**
     * Admin memverifikasi bukti transfer masuk dari pembeli.
     */
    public function verifyDeposit(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $admin = Auth::user();

        if ($transaction->status !== EscrowTransaction::STATUS_DEPOSIT_SUBMITTED) {
            return redirect()->back()->with('error', __('Transaksi tidak dalam status menunggu verifikasi deposit.'));
        }

        $transaction->update([
            'status' => EscrowTransaction::STATUS_FUNDS_HELD,
            'deposit_verified_at' => now(),
            'deposit_verified_by' => $admin->id,
        ]);

        $transaction->logTimeline(
            'admin',
            $admin->id,
            'funds_verified',
            __('Dana deposit sebesar Rp:amount telah diverifikasi sah oleh Admin (:name). Dana resmi diamankan dalam rekening escrow. Penjual diinstruksikan menyerahkan barang.', [
                'amount' => number_format($transaction->total_buyer_pay, 0, ',', '.'),
                'name' => $admin->display_name
            ])
        );

        return redirect()->back()->with('success', __('Deposit berhasil diverifikasi. Dana telah diamankan.'));
    }

    /**
     * Admin mencairkan dana ke rekening penjual luar dan upload bukti transfer keluar.
     */
    public function processPayout(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $admin = Auth::user();

        if ($transaction->status !== EscrowTransaction::STATUS_WAITING_PAYOUT) {
            return redirect()->back()->with('error', __('Hanya transaksi yang telah disetujui rilis yang dapat dicairkan.'));
        }

        $request->validate([
            'payout_proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $payoutPath = $request->file('payout_proof')->store('escrow_payouts', 'public');

        $transaction->update([
            'status' => EscrowTransaction::STATUS_COMPLETED,
            'payout_proof' => $payoutPath,
            'payout_at' => now(),
            'payout_by' => $admin->id,
            'admin_notes' => $request->input('admin_notes'),
        ]);

        $transaction->logTimeline(
            'admin',
            $admin->id,
            'payout_completed',
            __('Admin (:admin) telah mentransfer dana pencairan sebesar Rp:amount ke rekening penjual (:bank - :acc a.n :holder). Transaksi selesai sukses.', [
                'admin' => $admin->display_name,
                'amount' => number_format($transaction->total_seller_receive, 0, ',', '.'),
                'bank' => $transaction->seller_bank_name ?: '-',
                'acc' => $transaction->seller_account_number ?: '-',
                'holder' => $transaction->seller_account_holder ?: '-'
            ])
        );

        return redirect()->back()->with('success', __('Pencairan dana berhasil diproses. Transaksi rekber resmi ditutup.'));
    }

    /**
     * Resolusi Sengketa (Arbitrase oleh Admin).
     */
    public function resolveDispute(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $admin = Auth::user();

        if ($transaction->status !== EscrowTransaction::STATUS_DISPUTED) {
            return redirect()->back()->with('error', __('Transaksi tidak dalam status sengketa.'));
        }

        $request->validate([
            'decision' => 'required|in:release_to_seller,refund_to_buyer',
            'dispute_resolution' => 'required|string|max:1000',
        ]);

        $decision = $request->input('decision');
        $resolutionNote = $request->input('dispute_resolution');

        if ($decision === 'release_to_seller') {
            $transaction->update([
                'status' => EscrowTransaction::STATUS_WAITING_PAYOUT,
                'dispute_resolved_at' => now(),
                'dispute_resolution' => $resolutionNote,
            ]);

            $transaction->logTimeline(
                'admin',
                $admin->id,
                'dispute_resolved_seller',
                __('Keputusan Arbitrase Admin (:name): Penjual dimenangkan. Alasan: ":note". Transaksi dilanjutkan ke tahap pencairan dana ke penjual.', [
                    'name' => $admin->display_name,
                    'note' => $resolutionNote
                ])
            );

            return redirect()->back()->with('success', __('Sengketa diselesaikan: Dana disetujui untuk dirilis ke penjual.'));
        } else {
            // Refund to buyer
            $transaction->update([
                'status' => EscrowTransaction::STATUS_REFUNDED,
                'dispute_resolved_at' => now(),
                'dispute_resolution' => $resolutionNote,
            ]);

            $transaction->logTimeline(
                'admin',
                $admin->id,
                'dispute_resolved_buyer',
                __('Keputusan Arbitrase Admin (:name): Pembeli dimenangkan. Alasan: ":note". Dana dikembalikan (refund) kepada pembeli.', [
                    'name' => $admin->display_name,
                    'note' => $resolutionNote
                ])
            );

            return redirect()->back()->with('success', __('Sengketa diselesaikan: Transaksi di-refund ke pembeli.'));
        }
    }

    /**
     * Simpan Pengaturan Rekber (Tarif Fee Persentase & Rekening Toko).
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'fee_percent' => 'required|numeric|between:0,50',
            'bank_info' => 'required|string|max:2000',
        ]);

        BotSetting::setSetting('escrow.fee_percent', $request->fee_percent);
        BotSetting::setSetting('escrow.bank_info', $request->bank_info);

        // Hapus konfigurasi min_fee jika ada agar tidak lagi digunakan
        BotSetting::where('key', 'escrow.min_fee')->delete();

        return redirect()->back()->with('success', __('Pengaturan rekber berhasil diperbarui.'));
    }
}
