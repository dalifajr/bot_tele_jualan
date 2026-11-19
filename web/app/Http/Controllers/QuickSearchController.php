<?php

namespace App\Http\Controllers;

use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuickSearchController extends Controller
{
    public function search(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['groups' => []], 401);
        }

        $q = trim((string) $request->query('q', ''));
        $qLower = mb_strtolower($q);
        $groups = [];

        // 1. Navigasi Cepat (role-aware)
        $shortcuts = [
            ['title' => __('Dashboard'), 'subtitle' => __('Ringkasan utama akun'), 'url' => route('dashboard'), 'icon' => 'fas fa-home'],
            ['title' => __('Katalog Produk'), 'subtitle' => __('Jelajahi produk digital'), 'url' => route('catalog.index'), 'icon' => 'fas fa-store'],
            ['title' => __('Dompet Saldo'), 'subtitle' => __('Top-up saldo & checkout 1-klik'), 'url' => route('wallet.index'), 'icon' => 'fas fa-wallet'],
            ['title' => __('Favorit & Alert Stok'), 'subtitle' => __('Wishlist produk & pengingat restok'), 'url' => route('wishlist.index'), 'icon' => 'fas fa-heart'],
            ['title' => __('Pusat Garansi & Lisensi'), 'subtitle' => __('Pantau masa aktif garansi produk'), 'url' => route('warranties.index'), 'icon' => 'fas fa-shield-check'],
            ['title' => __('Pesanan Saya'), 'subtitle' => __('Riwayat transaksi belanja'), 'url' => route('orders.index'), 'icon' => 'fas fa-receipt'],
            ['title' => __('Profil & Keamanan'), 'subtitle' => __('Kata sandi, TOTP 2FA & sesi aktif'), 'url' => route('profile') . '#tab-keamanan', 'icon' => 'fas fa-shield-halved'],
            ['title' => __('Obrolan'), 'subtitle' => __('Pesan dengan penjual / admin'), 'url' => route('chat.index'), 'icon' => 'fas fa-comments'],
        ];

        if ($user->role === 'admin') {
            $shortcuts = array_merge($shortcuts, [
                ['title' => __('Dashboard Admin'), 'subtitle' => __('Analitik & ringkasan toko'), 'url' => route('admin.dashboard'), 'icon' => 'fas fa-chart-line'],
                ['title' => __('Kelola Pesanan (Admin)'), 'subtitle' => __('Konfirmasi pembayaran & pengiriman'), 'url' => route('admin.orders.index'), 'icon' => 'fas fa-cart-shopping'],
                ['title' => __('Top-Up Dompet Pelanggan'), 'subtitle' => __('Konfirmasi deposit saldo pembeli'), 'url' => route('admin.wallet.index'), 'icon' => 'fas fa-coins'],
                ['title' => __('Kelola Pelanggan'), 'subtitle' => __('Daftar akun & kontrol pengguna'), 'url' => route('admin.users.index'), 'icon' => 'fas fa-users'],
                ['title' => __('Keamanan & Peta Login'), 'subtitle' => __('Peta sebaran lokasi, analitik & riwayat login'), 'url' => route('admin.logins.index', ['tab' => 'peta']), 'icon' => 'fas fa-map-location-dot'],
                ['title' => __('Firewall & Aturan Blokir'), 'subtitle' => __('Blokir IP, subnet CIDR & ID Telegram'), 'url' => route('admin.security.firewall', ['tab' => 'firewall']), 'icon' => 'fas fa-shield-virus'],
                ['title' => __('Intelijen Ancaman & Anti-Spam'), 'subtitle' => __('IP agresif, target akun & multi-akun spam'), 'url' => route('admin.security.firewall', ['tab' => 'ancaman']), 'icon' => 'fas fa-crosshairs'],
                ['title' => __('Sesi Aktif & Postur Keamanan'), 'subtitle' => __('Pengawasan perangkat online & kebijakan anti-spam'), 'url' => route('admin.security.firewall', ['tab' => 'sesi-audit']), 'icon' => 'fas fa-laptop-medical'],
                ['title' => __('Kesehatan Sistem'), 'subtitle' => __('Monitor DB, disk, Telegram & antrean'), 'url' => route('admin.system.health'), 'icon' => 'fas fa-heart-pulse'],
                ['title' => __('Kelola Rekber'), 'subtitle' => __('Transaksi escrow aktif'), 'url' => route('admin.escrow.index'), 'icon' => 'fas fa-handshake'],
                ['title' => __('Permintaan Payout'), 'subtitle' => __('Persetujuan penarikan saldo seller'), 'url' => route('admin.withdrawals.index'), 'icon' => 'fas fa-money-bill-wave'],
            ]);
        } elseif ($user->role === 'seller') {
            $shortcuts = array_merge($shortcuts, [
                ['title' => __('Dashboard Seller'), 'subtitle' => __('Ringkasan pendapatan & stok'), 'url' => route('seller.dashboard'), 'icon' => 'fas fa-chart-pie'],
                ['title' => __('Analitik Penjualan Seller'), 'subtitle' => __('Tren omzet, jam ramai & kualitas stok'), 'url' => route('seller.analytics'), 'icon' => 'fas fa-chart-area'],
                ['title' => __('Produk Saya'), 'subtitle' => __('Kelola katalog toko Anda'), 'url' => route('seller.products.index'), 'icon' => 'fas fa-box-open'],
                ['title' => __('Pesanan Toko'), 'subtitle' => __('Pesanan masuk pelanggan'), 'url' => route('seller.orders.index'), 'icon' => 'fas fa-receipt'],
                ['title' => __('Keuangan & Penarikan'), 'subtitle' => __('Saldo wallet & ajukan payout'), 'url' => route('seller.finance.index'), 'icon' => 'fas fa-wallet'],
            ]);
        }

        if ($q === '') {
            $groups[] = [
                'label' => __('Akses Cepat'),
                'items' => array_slice($shortcuts, 0, 8),
            ];
            return response()->json(['groups' => $groups]);
        }

        $matchedShortcuts = array_values(array_filter($shortcuts, function ($item) use ($qLower) {
            return str_contains(mb_strtolower($item['title']), $qLower)
                || str_contains(mb_strtolower($item['subtitle']), $qLower);
        }));

        if (count($matchedShortcuts) > 0) {
            $groups[] = [
                'label' => __('Menu & Navigasi'),
                'items' => array_slice($matchedShortcuts, 0, 4),
            ];
        }

        // 2. Pencarian Produk
        $products = Product::where('is_suspended', false)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'price']);

        if ($products->isNotEmpty()) {
            $groups[] = [
                'label' => __('Produk'),
                'items' => $products->map(function ($p) {
                    return [
                        'title' => $p->name,
                        'subtitle' => 'Rp ' . number_format((int) $p->price, 0, ',', '.'),
                        'url' => route('catalog.show', $p->id),
                        'icon' => 'fas fa-box',
                    ];
                })->values()->all(),
            ];
        }

        // 3. Pencarian Pesanan (role-scoped)
        $orderQuery = Order::where('order_ref', 'like', "%{$q}%");
        if ($user->role === 'admin') {
            $orders = $orderQuery->orderByDesc('id')->limit(5)->get(['id', 'order_ref', 'status', 'total_amount']);
            if ($orders->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Pesanan'),
                    'items' => $orders->map(function ($o) {
                        return [
                            'title' => '#' . $o->order_ref,
                            'subtitle' => strtoupper((string) $o->status) . ' · Rp ' . number_format((int) $o->total_amount, 0, ',', '.'),
                            'url' => route('admin.orders.index', ['search' => $o->order_ref]),
                            'icon' => 'fas fa-receipt',
                        ];
                    })->values()->all(),
                ];
            }
        } elseif ($user->role === 'seller') {
            $orders = $orderQuery->where(function ($sub) use ($user) {
                $sub->where('customer_id', $user->id)
                    ->orWhereHas('items.product', fn ($pq) => $pq->where('creator_id', $user->id));
            })->orderByDesc('id')->limit(5)->get(['id', 'order_ref', 'status', 'total_amount', 'customer_id']);

            if ($orders->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Pesanan'),
                    'items' => $orders->map(function ($o) use ($user) {
                        $url = (int) $o->customer_id === (int) $user->id
                            ? route('orders.show', $o->id)
                            : route('seller.orders.index', ['search' => $o->order_ref]);
                        return [
                            'title' => '#' . $o->order_ref,
                            'subtitle' => strtoupper((string) $o->status) . ' · Rp ' . number_format((int) $o->total_amount, 0, ',', '.'),
                            'url' => $url,
                            'icon' => 'fas fa-receipt',
                        ];
                    })->values()->all(),
                ];
            }
        } else {
            $orders = $orderQuery->where('customer_id', $user->id)
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'order_ref', 'status', 'total_amount']);

            if ($orders->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Pesanan Saya'),
                    'items' => $orders->map(function ($o) {
                        return [
                            'title' => '#' . $o->order_ref,
                            'subtitle' => strtoupper((string) $o->status) . ' · Rp ' . number_format((int) $o->total_amount, 0, ',', '.'),
                            'url' => route('orders.show', $o->id),
                            'icon' => 'fas fa-receipt',
                        ];
                    })->values()->all(),
                ];
            }
        }

        // 4. Pencarian Rekber (Admin atau User berhak akses rekber)
        if ($user->role === 'admin' || (method_exists($user, 'canUseRekber') && $user->canUseRekber())) {
            $escrowQuery = EscrowTransaction::where(function ($sub) use ($q) {
                $sub->where('transaction_code', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            });

            if ($user->role !== 'admin') {
                $escrowQuery->where(function ($sub) use ($user) {
                    $sub->where('creator_id', $user->id)
                        ->orWhere('buyer_user_id', $user->id)
                        ->orWhere('seller_user_id', $user->id);
                });
            }

            $escrows = $escrowQuery->orderByDesc('id')->limit(4)->get(['transaction_code', 'title', 'status', 'amount']);
            if ($escrows->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Transaksi Rekber'),
                    'items' => $escrows->map(function ($e) use ($user) {
                        $url = $user->role === 'admin'
                            ? route('admin.escrow.show', $e->transaction_code)
                            : route('escrow.show', $e->transaction_code);
                        return [
                            'title' => $e->transaction_code . ' — ' . $e->title,
                            'subtitle' => strtoupper((string) $e->status) . ' · Rp ' . number_format((int) $e->amount, 0, ',', '.'),
                            'url' => $url,
                            'icon' => 'fas fa-handshake',
                        ];
                    })->values()->all(),
                ];
            }
        }

        // 5. Pencarian Pelanggan (khusus Admin)
        if ($user->role === 'admin') {
            $users = User::where(function ($sub) use ($q) {
                $sub->where('username', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })->orderByDesc('id')->limit(5)->get(['id', 'username', 'full_name', 'role', 'email']);

            if ($users->isNotEmpty()) {
                $groups[] = [
                    'label' => __('Pengguna'),
                    'items' => $users->map(function ($u) {
                        return [
                            'title' => ($u->full_name ?: $u->username) . ($u->username ? " (@{$u->username})" : ''),
                            'subtitle' => strtoupper((string) $u->role) . ($u->email ? " · {$u->email}" : ''),
                            'url' => route('admin.users.index', ['search' => $u->username ?: $u->full_name]),
                            'icon' => 'fas fa-user',
                        ];
                    })->values()->all(),
                ];
            }
        }

        return response()->json(['groups' => $groups]);
    }
}
