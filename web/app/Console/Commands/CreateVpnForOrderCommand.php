<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\VpnService;
use App\Models\VpnAccount;

class CreateVpnForOrderCommand extends Command
{
    protected $signature = 'vpn:create-for-order {order_id}';
    protected $description = 'Create VPN accounts for a specific order and output the configs';

    public function handle(VpnService $vpnService)
    {
        $orderId = $this->argument('order_id');
        $order = Order::with('items.product')->find($orderId);

        if (!$order) {
            $this->error("Order not found");
            return 1;
        }

        $outputs = [];

        foreach ($order->items as $item) {
            if ($item->product && $item->product->is_vpn) {
                // Periksa apakah sudah ada VPN account untuk item/order ini
                // (Untuk menghindari duplikasi jika dipanggil ulang)
                $existing = VpnAccount::where('order_id', $order->id)->get();
                if ($existing->count() > 0) {
                    foreach ($existing as $acc) {
                        $outputs[] = $acc->config_link;
                    }
                    continue; // asumsikan satu order VPN sudah dibuat
                }

                for ($i = 0; $i < $item->quantity; $i++) {
                    $username = $item->vpn_username;
                    if ($item->quantity > 1) {
                        $username .= '_' . ($i + 1);
                    }
                    
                    $res = $vpnService->createVpnAccount(
                        $item->product->vpn_protocol,
                        $username,
                        $item->vpn_password,
                        $item->product->vpn_duration_days
                    );
                    
                    $vpn = VpnAccount::create([
                        'user_id' => $order->customer_id,
                        'order_id' => $order->id,
                        'protocol' => $item->product->vpn_protocol,
                        'username' => $username,
                        'password' => $item->vpn_password,
                        'config_link' => $res['output'] ?? 'Failed to generate',
                        'expired_at' => now()->addDays($item->product->vpn_duration_days),
                        'status' => $res['success'] ? 'active' : 'failed'
                    ]);

                    $outputs[] = $vpn->config_link;
                }
            }
        }

        // Output JSON encoded array of configs so Python can parse it easily
        echo json_encode($outputs);
        return 0;
    }
}
