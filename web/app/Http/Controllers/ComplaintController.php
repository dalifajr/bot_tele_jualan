<?php

namespace App\Http\Controllers;

use App\Models\ComplaintCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    public function index()
    {
        $complaints = ComplaintCase::where('customer_id', Auth::id())
            ->with(['order.items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        return view('complaints.index', compact('complaints'));
    }

    public function show($id)
    {
        $complaint = ComplaintCase::where('customer_id', Auth::id())
            ->with(['order.items.product', 'order.stockUnits'])
            ->findOrFail($id);
            
        return view('complaints.show', compact('complaint'));
    }

    public function reopen($id)
    {
        $complaint = ComplaintCase::where('customer_id', Auth::id())->findOrFail($id);
        
        if (!in_array($complaint->status, ['done', 'rejected', 'refund_requested'])) {
            return redirect()->back()->with('error', 'Komplain belum ditutup atau diselesaikan.');
        }

        if ($complaint->reopen_count >= 3) {
            return redirect()->back()->with('error', 'Batas maksimal pembukaan ulang (3 kali) telah tercapai.');
        }

        if (!$complaint->order || !$complaint->order->is_warranty_active) {
            return redirect()->back()->with('error', 'Garansi toko untuk pesanan ini telah kedaluwarsa sehingga komplain tidak dapat dibuka kembali.');
        }

        $complaint->update([
            'status' => 'review',
            'reopen_count' => $complaint->reopen_count + 1,
            'closed_at' => null,
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'customer_reopen_complaint',
            'actor_id' => Auth::id(),
            'entity_type' => 'complaint_case',
            'entity_id' => $complaint->id,
            'detail' => "Reopened complaint #{$complaint->complaint_ref} (Attempt: {$complaint->reopen_count})",
            'created_at' => now(),
        ]);

        // Notify the seller in the web app
        $sellerId = $complaint->order->items->first()->product->creator_id ?? null;
        $message = "Pelanggan membuka kembali (reopen) komplain {$complaint->complaint_ref}";
        if ($sellerId) {
            $seller = \App\Models\User::find($sellerId);
            if ($seller) {
                $seller->notify(new \App\Notifications\ComplaintNotification($complaint, 'new', $message));
            }
        }
        
        // Notify admins
        \App\Models\User::where('role', 'admin')->get()->each(function ($admin) use ($complaint, $message) {
            $admin->notify(new \App\Notifications\ComplaintNotification($complaint, 'new', $message));
        });

        return redirect()->route('customer.complaints.show', $complaint->id)->with('success', 'Komplain berhasil dibuka kembali dan sedang ditinjau ulang oleh penjual.');
    }
}
