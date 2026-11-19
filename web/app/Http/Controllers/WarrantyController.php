<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class WarrantyController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $deliveredOrders = Order::with(['items.product', 'complaints'])
            ->where('customer_id', $user->id)
            ->where('status', 'delivered')
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $entries = collect();

        foreach ($deliveredOrders as $order) {
            $baseDate = $order->delivered_at ?? $order->paid_at ?? $order->created_at;
            $latestComplaint = $order->complaints->sortByDesc('id')->first();

            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }

                $warrantyDays = (int) ($product->warranty_days ?? 0);
                $isVpn = (bool) ($product->is_vpn ?? false);
                $durationDays = $isVpn ? (int) ($product->vpn_duration_days ?? 0) : $warrantyDays;

                if ($warrantyDays <= 0 && !$isVpn) {
                    continue;
                }

                $expiresAt = $baseDate ? $baseDate->copy()->addDays(max($warrantyDays, $durationDays)) : null;
                $daysRemaining = $expiresAt ? (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false) : 0;

                if (!$expiresAt || $expiresAt->isPast()) {
                    $status = 'expired';
                } elseif ($daysRemaining <= 3) {
                    $status = 'expiring_soon';
                } else {
                    $status = 'active';
                }

                $entries->push([
                    'order' => $order,
                    'item' => $item,
                    'product' => $product,
                    'warranty_days' => max($warrantyDays, $durationDays),
                    'started_at' => $baseDate,
                    'expires_at' => $expiresAt,
                    'days_remaining' => max(0, $daysRemaining),
                    'status' => $status,
                    'complaint' => $latestComplaint,
                    'is_vpn' => $isVpn,
                ]);
            }
        }

        $activeCount = $entries->whereIn('status', ['active', 'expiring_soon'])->count();
        $expiringSoonCount = $entries->where('status', 'expiring_soon')->count();
        $expiredCount = $entries->where('status', 'expired')->count();

        return view('warranties.index', compact(
            'entries',
            'activeCount',
            'expiringSoonCount',
            'expiredCount'
        ));
    }
}
