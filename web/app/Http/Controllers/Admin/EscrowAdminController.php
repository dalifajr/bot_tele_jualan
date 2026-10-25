<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EscrowController;
use App\Models\BotSetting;
use App\Models\EscrowTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Tampilan form inisiasi room rekber baru oleh Admin.
     */
    public function create()
    {
        $users = User::orderBy('full_name')->get();
        $feePercent = (float)(BotSetting::where('key', 'escrow.fee_percent')->value('value') ?? 1.5);

        return view('admin.escrow.create', compact('users', 'feePercent'));
    }

    /**
     * Simpan inisiasi room rekber baru oleh Admin.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'amount' => 'required|numeric|min:10000',
            'fee_payer' => 'required|in:buyer,seller,split',
            'inspection_hours' => 'required|integer|min:1|max:168',
            'buyer_type' => 'required|in:registered,external',
            'buyer_user_id' => 'nullable|required_if:buyer_type,registered|exists:users,id',
            'buyer_name' => 'required|string|max:100',
            'buyer_contact' => 'required|string|max:100',
            'seller_type' => 'required|in:registered,external',
            'seller_user_id' => 'nullable|required_if:seller_type,registered|exists:users,id',
            'seller_name' => 'required|string|max:100',
            'seller_contact' => 'required|string|max:100',
            'seller_bank_name' => 'nullable|string|max:50',
            'seller_account_number' => 'nullable|string|max:50',
            'seller_account_holder' => 'nullable|string|max:100',
        ]);

        $code = 'RKB-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $publicToken = Str::random(32);
        $amount = (float)$request->amount;
        $feeCalc = EscrowController::calculateFee($amount, $request->fee_payer);
        $uniqueCode = rand(1, 250);
        $totalBuyerPay = $feeCalc['total_buyer_pay'] + $uniqueCode;

        $transaction = EscrowTransaction::create([
            'transaction_code' => $code,
            'creator_id' => Auth::id(),
            'creator_role' => 'buyer',
            'buyer_user_id' => $request->buyer_type === 'registered' ? $request->buyer_user_id : null,
            'buyer_name' => $request->buyer_name,
            'buyer_contact' => $request->buyer_contact,
            'seller_user_id' => $request->seller_type === 'registered' ? $request->seller_user_id : null,
            'seller_name' => $request->seller_name,
            'seller_contact' => $request->seller_contact,
            'seller_bank_name' => $request->seller_bank_name,
            'seller_account_number' => $request->seller_account_number,
            'seller_account_holder' => $request->seller_account_holder,
            'title' => $request->title,
            'description' => $request->description,
            'amount' => $amount,
            'fee_amount' => $feeCalc['fee_amount'],
            'fee_payer' => $request->fee_payer,
            'unique_code' => $uniqueCode,
            'total_buyer_pay' => $totalBuyerPay,
            'total_seller_receive' => $feeCalc['total_seller_receive'],
            'inspection_hours' => (int)$request->inspection_hours,
            'status' => EscrowTransaction::STATUS_WAITING_DEPOSIT,
            'public_token' => $publicToken,
        ]);

        $transaction->logTimeline(
            'admin',
            Auth::id(),
            'created_by_admin',
            __('Ruang Rekber dibuat langsung oleh Admin (:admin) untuk transaksi antara :buyer dan :seller.', [
                'admin' => Auth::user()->display_name,
                'buyer' => $transaction->buyer_name,
                'seller' => $transaction->seller_name,
            ])
        );

        return redirect()->route('admin.escrow.show', $transaction->transaction_code)
            ->with('success', __('Ruang rekber baru berhasil diinisiasi oleh Admin.'));
    }

    /**
     * Tampilan form edit transaksi rekber oleh Admin.
     */
    public function edit(string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $users = User::orderBy('full_name')->get();
        $feePercent = (float)(BotSetting::where('key', 'escrow.fee_percent')->value('value') ?? 1.5);

        return view('admin.escrow.edit', compact('transaction', 'users', 'feePercent'));
    }

    /**
     * Simpan pembaruan data transaksi rekber oleh Admin.
     */
    public function update(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'amount' => 'required|numeric|min:10000',
            'fee_payer' => 'required|in:buyer,seller,split',
            'inspection_hours' => 'required|integer|min:1|max:168',
            'buyer_name' => 'required|string|max:100',
            'buyer_contact' => 'required|string|max:100',
            'seller_name' => 'required|string|max:100',
            'seller_contact' => 'required|string|max:100',
            'seller_bank_name' => 'nullable|string|max:50',
            'seller_account_number' => 'nullable|string|max:50',
            'seller_account_holder' => 'nullable|string|max:100',
            'status' => 'required|in:waiting_deposit,deposit_submitted,funds_held,delivered,waiting_payout,completed,disputed,refunded,cancelled',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $transaction->status;
        $amount = (float)$request->amount;
        $feeCalc = EscrowController::calculateFee($amount, $request->fee_payer);
        $uniqueCode = $transaction->unique_code ?: rand(1, 250);
        $totalBuyerPay = $feeCalc['total_buyer_pay'] + $uniqueCode;

        $transaction->update([
            'title' => $request->title,
            'description' => $request->description,
            'amount' => $amount,
            'fee_amount' => $feeCalc['fee_amount'],
            'fee_payer' => $request->fee_payer,
            'unique_code' => $uniqueCode,
            'total_buyer_pay' => $totalBuyerPay,
            'total_seller_receive' => $feeCalc['total_seller_receive'],
            'inspection_hours' => (int)$request->inspection_hours,
            'buyer_name' => $request->buyer_name,
            'buyer_contact' => $request->buyer_contact,
            'seller_name' => $request->seller_name,
            'seller_contact' => $request->seller_contact,
            'seller_bank_name' => $request->seller_bank_name,
            'seller_account_number' => $request->seller_account_number,
            'seller_account_holder' => $request->seller_account_holder,
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ]);

        $statusChangedNote = ($oldStatus !== $request->status) ? " Status diubah dari '{$oldStatus}' menjadi '{$request->status}'." : "";

        $transaction->logTimeline(
            'admin',
            Auth::id(),
            'updated_by_admin',
            __('Admin (:admin) telah memperbarui rincian transaksi rekber.:notes', [
                'admin' => Auth::user()->display_name,
                'notes' => $statusChangedNote,
            ])
        );

        return redirect()->route('admin.escrow.show', $transaction->transaction_code)
            ->with('success', __('Rincian transaksi rekber berhasil diperbarui.'));
    }

    /**
     * Hapus permanen room rekber beserta berkas lampirannya oleh Admin.
     */
    public function destroy(string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();

        // Hapus file deposit proof jika ada
        if ($transaction->deposit_proof) {
            Storage::disk('public')->delete($transaction->deposit_proof);
        }

        // Hapus file payout proof jika ada
        if ($transaction->payout_proof) {
            Storage::disk('public')->delete($transaction->payout_proof);
        }

        // Hapus file lampiran chat jika ada
        foreach ($transaction->messages as $msg) {
            if ($msg->attachment_path) {
                Storage::disk('public')->delete($msg->attachment_path);
            }
        }

        // Hapus relasi database
        $transaction->messages()->delete();
        $transaction->timelines()->delete();
        $transaction->delete();

        return redirect()->route('admin.escrow.index')
            ->with('success', __('Transaksi rekber :code dan seluruh data terkait berhasil dihapus.', ['code' => $code]));
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
