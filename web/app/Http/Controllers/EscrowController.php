<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\EscrowMessage;
use App\Models\EscrowTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EscrowController extends Controller
{
    /**
     * Menghitung fee rekber berdasarkan persentase platform saja (tanpa minimum fee).
     */
    public static function calculateFee(float $amount, string $feePayer = 'buyer'): array
    {
        $feePercent = (float)(BotSetting::where('key', 'escrow.fee_percent')->value('value') ?? 1.5);
        $feeAmount = round(($amount * $feePercent) / 100);

        if ($feePayer === 'seller') {
            $totalBuyerPay = $amount;
            $totalSellerReceive = max(0, $amount - $feeAmount);
        } elseif ($feePayer === 'split') {
            $halfFee = round($feeAmount / 2);
            $totalBuyerPay = $amount + $halfFee;
            $totalSellerReceive = max(0, $amount - ($feeAmount - $halfFee));
        } else {
            // buyer pays
            $totalBuyerPay = $amount + $feeAmount;
            $totalSellerReceive = $amount;
        }

        return [
            'fee_amount' => $feeAmount,
            'fee_percent' => $feePercent,
            'total_buyer_pay' => $totalBuyerPay,
            'total_seller_receive' => $totalSellerReceive,
        ];
    }

    /**
     * Daftar transaksi rekber untuk pengguna internal.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $status = $request->query('status');

        $query = EscrowTransaction::where(function ($q) use ($user) {
            $q->where('creator_id', $user->id)
              ->orWhere('buyer_user_id', $user->id)
              ->orWhere('seller_user_id', $user->id);
        });

        if ($status && $status !== 'all') {
            if ($status === 'active') {
                $query->whereIn('status', [
                    EscrowTransaction::STATUS_WAITING_DEPOSIT,
                    EscrowTransaction::STATUS_DEPOSIT_SUBMITTED,
                    EscrowTransaction::STATUS_FUNDS_HELD,
                    EscrowTransaction::STATUS_DELIVERED,
                    EscrowTransaction::STATUS_WAITING_PAYOUT,
                    EscrowTransaction::STATUS_DISPUTED,
                ]);
            } else {
                $query->where('status', $status);
            }
        }

        $transactions = $query->orderBy('updated_at', 'desc')->paginate(10);

        return view('escrow.index', compact('transactions', 'status'));
    }

    /**
     * Halaman form pembuatan transaksi rekber baru.
     */
    public function create()
    {
        $feePercent = (float)(BotSetting::where('key', 'escrow.fee_percent')->value('value') ?? 1.5);

        return view('escrow.create', compact('feePercent'));
    }

    /**
     * Simpan inisiasi transaksi rekber baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'creator_role' => 'required|in:buyer,seller',
            'partner_name' => 'required|string|max:100',
            'partner_contact' => 'required|string|max:100',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'amount' => 'required|numeric|min:10000',
            'fee_payer' => 'required|in:buyer,seller,split',
            'inspection_hours' => 'nullable|integer|between:1,168',
            'seller_bank_name' => 'nullable|string|max:50',
            'seller_account_number' => 'nullable|string|max:50',
            'seller_account_holder' => 'nullable|string|max:100',
        ]);

        $user = Auth::user();
        $amount = (float)$validated['amount'];
        $feeCalc = self::calculateFee($amount, $validated['fee_payer']);

        if ($validated['creator_role'] === 'buyer') {
            $buyerUserId = $user->id;
            $buyerName = $user->display_name;
            $buyerContact = $user->telegram_id ? ('@' . $user->username) : ($user->email ?: 'ID: ' . $user->id);
            $sellerUserId = null;
            $sellerName = $validated['partner_name'];
            $sellerContact = $validated['partner_contact'];
        } else {
            $sellerUserId = $user->id;
            $sellerName = $user->display_name;
            $sellerContact = $user->telegram_id ? ('@' . $user->username) : ($user->email ?: 'ID: ' . $user->id);
            $buyerUserId = null;
            $buyerName = $validated['partner_name'];
            $buyerContact = $validated['partner_contact'];
        }

        $transaction = EscrowTransaction::create([
            'creator_id' => $user->id,
            'creator_role' => $validated['creator_role'],
            'buyer_user_id' => $buyerUserId,
            'buyer_name' => $buyerName,
            'buyer_contact' => $buyerContact,
            'seller_user_id' => $sellerUserId,
            'seller_name' => $sellerName,
            'seller_contact' => $sellerContact,
            'seller_bank_name' => $validated['seller_bank_name'] ?? null,
            'seller_account_number' => $validated['seller_account_number'] ?? null,
            'seller_account_holder' => $validated['seller_account_holder'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount' => $amount,
            'fee_amount' => $feeCalc['fee_amount'],
            'fee_payer' => $validated['fee_payer'],
            'total_buyer_pay' => $feeCalc['total_buyer_pay'],
            'total_seller_receive' => $feeCalc['total_seller_receive'],
            'inspection_hours' => $validated['inspection_hours'] ?? 24,
            'status' => EscrowTransaction::STATUS_WAITING_DEPOSIT,
        ]);

        $transaction->logTimeline(
            'system',
            $user->id,
            'created',
            __('Transaksi rekber berhasil dibuat oleh :name (:role). Menunggu pembayaran deposit dari pembeli.', [
                'name' => $user->display_name,
                'role' => $validated['creator_role'] === 'buyer' ? 'Pembeli' : 'Penjual'
            ])
        );

        return redirect()->route('escrow.show', $transaction->transaction_code)
            ->with('success', __('Transaksi rekber berhasil dibuat.'));
    }

    /**
     * Dapatkan payload QRIS dinamis dan gambar QRIS cadangan untuk transaksi rekber.
     */
    protected function getQrisData(EscrowTransaction $transaction): array
    {
        $staticQris = BotSetting::where('key', 'qris_static_payload')->value('value');
        $qrisImagePath = BotSetting::where('key', 'qris_image_path')->value('value');
        $dynamicQris = null;

        if ($staticQris && $transaction->total_buyer_pay > 0) {
            try {
                $dynamicQris = \App\Services\QrisService::buildDynamicPayload($staticQris, (int)$transaction->total_buyer_pay);
            } catch (\Throwable $e) {
                \Log::warning("Escrow Dynamic QRIS Generation Failed for {$transaction->transaction_code}: " . $e->getMessage());
            }
        }

        return [
            'dynamicQris' => $dynamicQris,
            'qrisImagePath' => $qrisImagePath,
        ];
    }

    /**
     * Ruang transaksi rekber internal user.
     */
    public function show(string $code)
    {
        $user = Auth::user();
        $transaction = EscrowTransaction::with(['timelines.actor', 'messages.sender'])
            ->where('transaction_code', $code)
            ->firstOrFail();

        if (!$transaction->isParticipant($user)) {
            abort(403, __('Anda tidak memiliki akses ke transaksi rekber ini.'));
        }

        $bankInfo = BotSetting::where('key', 'escrow.bank_info')->value('value') ?? "Bank BCA: 1234567890\na.n Dzulfikrialifajri Store";
        $qrisData = $this->getQrisData($transaction);

        return view('escrow.show', array_merge(compact('transaction', 'bankInfo'), $qrisData));
    }

    /**
     * Ruang transaksi rekber publik via token (untuk pihak luar tanpa login).
     */
    public function showPublic(string $token)
    {
        $transaction = EscrowTransaction::with(['timelines.actor', 'messages.sender'])
            ->where('public_token', $token)
            ->firstOrFail();

        $bankInfo = BotSetting::where('key', 'escrow.bank_info')->value('value') ?? "Bank BCA: 1234567890\na.n Dzulfikrialifajri Store";
        $isPublic = true;
        $qrisData = $this->getQrisData($transaction);

        return view('escrow.show', array_merge(compact('transaction', 'bankInfo', 'isPublic'), $qrisData));
    }

    /**
     * Upload bukti transfer pembayaran pembeli.
     */
    public function uploadDeposit(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $user = Auth::user();

        if (!$transaction->isBuyer($user) && $user->role !== 'admin') {
            abort(403, __('Hanya pembeli yang dapat mengunggah bukti pembayaran deposit.'));
        }

        return $this->processDepositUpload($request, $transaction, $user->display_name, $user->id);
    }

    public function uploadDepositPublic(Request $request, string $token)
    {
        $transaction = EscrowTransaction::where('public_token', $token)->firstOrFail();
        return $this->processDepositUpload($request, $transaction, $transaction->buyer_name, null);
    }

    protected function processDepositUpload(Request $request, EscrowTransaction $transaction, string $actorName, ?int $actorId)
    {
        if (!in_array($transaction->status, [EscrowTransaction::STATUS_WAITING_DEPOSIT, EscrowTransaction::STATUS_DEPOSIT_SUBMITTED])) {
            return redirect()->back()->with('error', __('Pembayaran tidak dapat diunggah pada status transaksi saat ini.'));
        }

        $request->validate([
            'deposit_proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $path = $request->file('deposit_proof')->store('escrow_deposits', 'public');

        $transaction->update([
            'deposit_proof' => $path,
            'status' => EscrowTransaction::STATUS_DEPOSIT_SUBMITTED,
        ]);

        $transaction->logTimeline(
            'buyer',
            $actorId,
            'deposit_submitted',
            __('Bukti pembayaran deposit telah diunggah oleh :name. Menunggu konfirmasi verifikasi dari Admin.', ['name' => $actorName])
        );

        return redirect()->back()->with('success', __('Bukti pembayaran berhasil diunggah. Menunggu verifikasi admin.'));
    }

    /**
     * Penjual mengunggah bukti serah terima (barang/akun/credentials).
     */
    public function uploadDelivery(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $user = Auth::user();

        if (!$transaction->isSeller($user) && $user->role !== 'admin') {
            abort(403, __('Hanya penjual yang dapat mengunggah data penyerahan barang.'));
        }

        return $this->processDeliveryUpload($request, $transaction, $user->display_name, $user->id);
    }

    public function uploadDeliveryPublic(Request $request, string $token)
    {
        $transaction = EscrowTransaction::where('public_token', $token)->firstOrFail();
        return $this->processDeliveryUpload($request, $transaction, $transaction->seller_name, null);
    }

    protected function processDeliveryUpload(Request $request, EscrowTransaction $transaction, string $actorName, ?int $actorId)
    {
        if (!in_array($transaction->status, [EscrowTransaction::STATUS_FUNDS_HELD, EscrowTransaction::STATUS_DELIVERED])) {
            return redirect()->back()->with('error', __('Penyerahan barang hanya bisa dilakukan setelah dana diamankan oleh admin.'));
        }

        $request->validate([
            'delivery_text' => 'required|string|max:3000',
            'delivery_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,zip,txt|max:10240',
            'seller_bank_name' => 'nullable|string|max:50',
            'seller_account_number' => 'nullable|string|max:50',
            'seller_account_holder' => 'nullable|string|max:100',
        ]);

        $deliveryContent = $request->input('delivery_text');
        if ($request->hasFile('delivery_file')) {
            $filePath = $request->file('delivery_file')->store('escrow_delivery', 'public');
            $deliveryContent .= "\n[File Lampiran: " . Storage::url($filePath) . "]";
        }

        $updateData = [
            'delivery_proof' => $deliveryContent,
            'delivered_at' => now(),
            'status' => EscrowTransaction::STATUS_DELIVERED,
        ];

        if ($request->filled('seller_bank_name')) $updateData['seller_bank_name'] = $request->input('seller_bank_name');
        if ($request->filled('seller_account_number')) $updateData['seller_account_number'] = $request->input('seller_account_number');
        if ($request->filled('seller_account_holder')) $updateData['seller_account_holder'] = $request->input('seller_account_holder');

        $transaction->update($updateData);

        $transaction->logTimeline(
            'seller',
            $actorId,
            'delivered',
            __('Penjual (:name) telah menyerahkan data/akun. Pembeli memiliki waktu :hours jam untuk memeriksa pesanan.', [
                'name' => $actorName,
                'hours' => $transaction->inspection_hours
            ])
        );

        return redirect()->back()->with('success', __('Bukti penyerahan berhasil disimpan. Pembeli sedang memeriksa data.'));
    }

    /**
     * Pembeli mengonfirmasi pesanan sesuai dan meminta pelepasan dana ke penjual.
     */
    public function confirmComplete(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $user = Auth::user();

        if (!$transaction->isBuyer($user) && $user->role !== 'admin') {
            abort(403, __('Hanya pembeli yang berhak mengonfirmasi penyelesaian pesanan.'));
        }

        return $this->processComplete($transaction, $user->display_name, $user->id);
    }

    public function confirmCompletePublic(Request $request, string $token)
    {
        $transaction = EscrowTransaction::where('public_token', $token)->firstOrFail();
        return $this->processComplete($transaction, $transaction->buyer_name, null);
    }

    protected function processComplete(EscrowTransaction $transaction, string $actorName, ?int $actorId)
    {
        if ($transaction->status !== EscrowTransaction::STATUS_DELIVERED) {
            return redirect()->back()->with('error', __('Hanya pesanan yang sudah dikirim yang dapat dikonfirmasi selesai.'));
        }

        $transaction->update([
            'status' => EscrowTransaction::STATUS_WAITING_PAYOUT,
        ]);

        $transaction->logTimeline(
            'buyer',
            $actorId,
            'confirmed',
            __('Pembeli (:name) mengonfirmasi barang telah diterima dan sesuai. Menunggu admin mentransfer dana pencairan ke rekening penjual.', ['name' => $actorName])
        );

        return redirect()->back()->with('success', __('Konfirmasi berhasil. Admin akan segera mentransfer dana ke rekening penjual.'));
    }

    /**
     * Pengajuan sengketa / komplain.
     */
    public function openDispute(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $user = Auth::user();

        if (!$transaction->isParticipant($user)) {
            abort(403, __('Anda tidak memiliki hak untuk mengajukan komplain pada transaksi ini.'));
        }

        return $this->processOpenDispute($request, $transaction, $user->display_name, $user->id);
    }

    public function openDisputePublic(Request $request, string $token)
    {
        $transaction = EscrowTransaction::where('public_token', $token)->firstOrFail();
        return $this->processOpenDispute($request, $transaction, $transaction->buyer_name ?: $transaction->seller_name, null);
    }

    protected function processOpenDispute(Request $request, EscrowTransaction $transaction, string $actorName, ?int $actorId)
    {
        if (!in_array($transaction->status, [EscrowTransaction::STATUS_FUNDS_HELD, EscrowTransaction::STATUS_DELIVERED, EscrowTransaction::STATUS_WAITING_PAYOUT])) {
            return redirect()->back()->with('error', __('Sengketa hanya dapat diajukan setelah dana ditampung oleh rekber.'));
        }

        $request->validate([
            'dispute_reason' => 'required|string|max:1000',
        ]);

        $transaction->update([
            'status' => EscrowTransaction::STATUS_DISPUTED,
            'dispute_reason' => $request->input('dispute_reason'),
            'dispute_opened_by' => $actorId,
        ]);

        $transaction->logTimeline(
            'system',
            $actorId,
            'disputed',
            __('Sengketa dibuka oleh :name dengan alasan: ":reason". Transaksi dibekukan untuk penyelidikan Admin.', [
                'name' => $actorName,
                'reason' => $request->input('dispute_reason')
            ])
        );

        return redirect()->back()->with('warning', __('Sengketa telah dibuka. Admin akan menengahi dan memeriksa bukti kedua belah pihak.'));
    }

    /**
     * Kirim pesan di thread diskusi rekber.
     */
    public function sendMessage(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $user = Auth::user();

        if (!$transaction->isParticipant($user)) {
            abort(403);
        }

        $role = $user->role === 'admin' ? 'admin' : ($transaction->isBuyer($user) ? 'buyer' : 'seller');
        return $this->processSendMessage($request, $transaction, $role, $user->display_name, $user->id);
    }

    public function sendMessagePublic(Request $request, string $token)
    {
        $transaction = EscrowTransaction::where('public_token', $token)->firstOrFail();
        // Public user role is seller by default if creator was buyer, or buyer if creator was seller
        $role = $transaction->creator_role === 'buyer' ? 'seller' : 'buyer';
        $name = $role === 'seller' ? $transaction->seller_name : $transaction->buyer_name;

        return $this->processSendMessage($request, $transaction, $role, $name, null);
    }

    protected function processSendMessage(Request $request, EscrowTransaction $transaction, string $role, string $name, ?int $senderId)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('escrow_chats', 'public');
        }

        $transaction->messages()->create([
            'sender_id' => $senderId,
            'sender_role' => $role,
            'sender_name' => $name,
            'message' => $request->input('message'),
            'attachment_path' => $attachmentPath,
        ]);

        return redirect()->back()->with('success', __('Pesan berhasil dikirim.'));
    }

    /**
     * Batalkan transaksi sebelum dana masuk.
     */
    public function cancel(Request $request, string $code)
    {
        $transaction = EscrowTransaction::where('transaction_code', $code)->firstOrFail();
        $user = Auth::user();

        if (!$transaction->isParticipant($user)) {
            abort(403);
        }

        if ($transaction->status !== EscrowTransaction::STATUS_WAITING_DEPOSIT) {
            return redirect()->back()->with('error', __('Transaksi tidak dapat dibatalkan secara sepihak setelah pembayaran diunggah.'));
        }

        $transaction->update(['status' => EscrowTransaction::STATUS_CANCELLED]);

        $transaction->logTimeline(
            'system',
            $user->id,
            'cancelled',
            __('Transaksi dibatalkan oleh :name sebelum dana ditransfer.', ['name' => $user->display_name])
        );

        return redirect()->route('escrow.index')->with('success', __('Transaksi berhasil dibatalkan.'));
    }
}
