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
        $order = Order::with('items.product.vpnServer')->find($orderId);

        if (!$order) {
            $this->error("Order not found");
            return 1;
        }

        $outputs = [];

        foreach ($order->items as $item) {
            if ($item->product && $item->product->is_vpn) {
                $existing = VpnAccount::where('order_id', $order->id)->get();
                if ($existing->count() > 0) {
                    foreach ($existing as $acc) {
                        if ($acc->status === 'failed' || $acc->status === 'provisioning') {
                            $acc = $vpnService->provisionAccount(
                                $acc,
                                max(1, (int) ($item->product->vpn_duration_days ?: 30)),
                                "order-{$order->order_ref}-acct-{$acc->id}",
                                (string) ($item->product->vpn_sni_mode ?? '3')
                            );
                        }
                        $outputs[] = $acc->config_link;
                    }
                    continue;
                }

                $resolvedServer = $vpnService->resolveServer(null, $item->product);
                $days = max(1, (int) ($item->product->vpn_duration_days ?: 30));
                $quotaGb = max(0, (int) ($item->product->vpn_quota_gb ?? 0));
                $ipLimit = max(1, (int) ($item->product->vpn_ip_limit ?? 1));
                $sniMode = (string) ($item->product->vpn_sni_mode ?? '3');

                for ($i = 0; $i < $item->quantity; $i++) {
                    $username = $item->vpn_username;
                    if ($item->quantity > 1) {
                        $username .= '_' . ($i + 1);
                    }

                    $vpn = VpnAccount::create([
                        'user_id' => $order->customer_id,
                        'order_id' => $order->id,
                        'vpn_server_id' => ($resolvedServer && $resolvedServer->exists) ? $resolvedServer->id : null,
                        'protocol' => $item->product->vpn_protocol,
                        'username' => $username,
                        'password' => $item->vpn_password,
                        'quota_gb' => $quotaGb,
                        'ip_limit' => $ipLimit,
                        'external_ref' => $order->order_ref,
                        'expired_at' => now()->addDays($days),
                        'status' => 'provisioning',
                    ]);

                    $vpn = $vpnService->provisionAccount(
                        $vpn,
                        $days,
                        "order-{$order->order_ref}-acct-{$vpn->id}",
                        $sniMode
                    );

                    $outputs[] = $vpn->config_link;
                }
            }
        }

        echo json_encode($outputs);
        return 0;
    }
}
