<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $status = $request->query('status');

        $query = Order::where('customer_id', $userId)->with(['items.product', 'stockUnits'])->orderByDesc('id');

        if ($status && in_array($status, ['pending_payment', 'paid', 'delivered', 'cancelled', 'expired'])) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(15);

        return view('orders.index', compact('orders', 'status'));
    }

    public function show($id)
    {
        $order = Order::where('customer_id', Auth::id())
            ->with(['items.product', 'stockUnits', 'complaintCase', 'vpnAccounts'])
            ->findOrFail($id);

        return view('orders.show', compact('order'));
    }

    public function cancel($id, \App\Services\OrderService $orderService)
    {
        $order = Order::where('customer_id', Auth::id())->findOrFail($id);

        try {
            $orderService->cancelOrder($order, 'cancelled_by_customer', Auth::id());
            return redirect()->back()->with('success', 'Pesanan berhasil dibatalkan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membatalkan pesanan: ' . $e->getMessage());
        }
    }

    public function submitComplaint(Request $request, $id)
    {
        $order = Order::where('customer_id', Auth::id())
            ->with(['complaintCase'])
            ->findOrFail($id);

        if ($order->status !== 'delivered') {
            return redirect()->back()->with('error', 'Komplain hanya dapat diajukan untuk pesanan yang sudah selesai (delivered).');
        }

        if ($order->complaintCase) {
            return redirect()->back()->with('error', 'Klaim garansi / komplain sudah pernah diajukan untuk pesanan ini.');
        }

        if (!$order->is_warranty_active) {
            return redirect()->back()->with('error', 'Garansi toko untuk pesanan ini telah kedaluwarsa atau tidak berlaku.');
        }

        $request->validate([
            'complaint_text' => 'required|string|min:10|max:1000',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ], [
            'complaint_text.required' => 'Deskripsi keluhan wajib diisi.',
            'complaint_text.min' => 'Deskripsi keluhan minimal 10 karakter.',
            'complaint_text.max' => 'Deskripsi keluhan maksimal 1000 karakter.',
            'attachment.image' => 'Lampiran harus berupa gambar.',
            'attachment.mimes' => 'Lampiran harus berformat jpeg, png, jpg, atau gif.',
            'attachment.max' => 'Ukuran gambar maksimal 10MB.',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('complaints', 'public');
        }

        $complaintRef = 'CMP-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));

        $complaint = \App\Models\ComplaintCase::create([
            'complaint_ref' => $complaintRef,
            'customer_id' => Auth::id(),
            'customer_telegram_id' => Auth::user()->telegram_id ?: 0,
            'customer_username_snapshot' => Auth::user()->username,
            'order_id' => $order->id,
            'order_ref_snapshot' => $order->order_ref,
            'order_created_at_snapshot' => $order->created_at,
            'complaint_text' => $request->complaint_text,
            'attachment_path' => $attachmentPath,
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Services\TelegramService::notifySellerNewComplaint($complaint);

        // Notify the seller in the web app
        $sellerId = $order->items->first()->product->creator_id ?? null;
        if ($sellerId) {
            $seller = \App\Models\User::find($sellerId);
            if ($seller) {
                $seller->notify(new \App\Notifications\ComplaintNotification($complaint, 'new'));
            }
        }
        
        // Notify admins
        \App\Models\User::where('role', 'admin')->get()->each(function ($admin) use ($complaint) {
            $admin->notify(new \App\Notifications\ComplaintNotification($complaint, 'new'));
        });

        return redirect()->back()->with('success', 'Komplain / klaim garansi berhasil diajukan. Kami akan segera meninjau keluhan Anda.');
    }
}
