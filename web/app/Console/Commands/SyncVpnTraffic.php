<?php

namespace App\Console\Commands;

use App\Models\VpnServer;
use App\Services\VpnService;
use Illuminate\Console\Command;

class SyncVpnTraffic extends Command
{
    protected $signature = 'vpn:sync-traffic {--server= : Optional VPN server ID}';
    protected $description = 'Sinkronisasi lalu lintas data (Upload/Download) seluruh akun VPN dari panel secara persisten (anti-reset)';

    public function handle(VpnService $vpnService): int
    {
        $query = VpnServer::where('is_active', true)
            ->where('driver', VpnServer::DRIVER_VPNXRAY_API);

        if ($this->option('server')) {
            $query->where('id', (int) $this->option('server'));
        }

        $servers = $query->get();
        if ($servers->isEmpty()) {
            $this->line('Tidak ada server VPN REST API aktif.');
            return self::SUCCESS;
        }

        $totalUpdated = 0;
        foreach ($servers as $server) {
            $res = $vpnService->syncServerTraffic($server);
            if (!empty($res['success'])) {
                $updated = (int) ($res['updated'] ?? 0);
                $totalUpdated += $updated;
                $this->info("[{$server->name}] Traffic disinkronkan ({$updated} akun diperbarui).");
            } else {
                $this->warn("[{$server->name}] Gagal sinkronisasi traffic: " . ($res['message'] ?? 'Unknown error'));
            }
        }

        $this->line("Selesai. Total akun diperbarui: {$totalUpdated}.");
        return self::SUCCESS;
    }
}
