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
        $search = trim((string)$request->query('search'));

        $query = Order::where('customer_id', $userId)->with(['items.product.creator', 'stockUnits'])->orderByDesc('id');

        // Status Filtering (Simplified Categories)
        if ($status === 'pending_payment') {
            $query->where('status', 'pending_payment');
        } elseif ($status === 'delivered') {
            $query->whereIn('status', ['delivered', 'paid']);
        } elseif ($status === 'cancelled_expired' || $status === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'expired']);
        } elseif ($status && in_array($status, ['paid', 'expired'])) {
            $query->where('status', $status);
        }

        // Search by Product Name, Order Reference, and Nominal
        if ($search !== '') {
            $cleanNominal = preg_replace('/[^0-9]/', '', $search);
            $query->where(function ($q) use ($search, $cleanNominal) {
                $q->where('order_ref', 'like', "%{$search}%")
                  ->orWhereHas('items.product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });

                if (!empty($cleanNominal) && is_numeric($cleanNominal)) {
                    $q->orWhere('total_amount', (int)$cleanNominal)
                      ->orWhere('subtotal', (int)$cleanNominal);
                }
            });
        }

        $orders = $query->paginate(12)->withQueryString();

        return view('orders.index', compact('orders', 'status', 'search'));
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
            return redirect()->back()->with('success', __('Pesanan berhasil dibatalkan.'));
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
            return redirect()->back()->with('error', __('Komplain hanya dapat diajukan untuk pesanan yang sudah selesai (delivered).'));
        }

        if ($order->complaintCase) {
            return redirect()->back()->with('error', __('Klaim garansi / komplain sudah pernah diajukan untuk pesanan ini.'));
        }

        if (!$order->is_warranty_active) {
            return redirect()->back()->with('error', __('Garansi toko untuk pesanan ini telah kedaluwarsa atau tidak berlaku.'));
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
            $file = $request->file('attachment');
            $extension = $file->getClientOriginalExtension();
            // Force random filename to prevent arbitrary file upload bypass
            $filename = \Illuminate\Support\Str::random(40) . '.' . $extension;
            $attachmentPath = $file->storeAs('complaints', $filename, 'public');
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

        return redirect()->back()->with('success', __('Komplain / klaim garansi berhasil diajukan. Kami akan segera meninjau keluhan Anda.'));
    }
}
