<?php

namespace App\Console\Commands;

use App\Models\VpnServer;
use App\Services\VpnService;
use Illuminate\Console\Command;

class SyncVpnAccountStatuses extends Command
{
    protected $signature = 'vpn:sync-status {--server= : Optional VPN server ID}';
    protected $description = 'Sinkronisasi status akun VPN (aktif/auto-suspend/expired) dari panel VPS dan kirim notifikasi auto-suspend ke pelanggan';

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

        $totalChecked = 0;
        $totalSuspendedNotified = 0;
        $totalUnsuspendedNotified = 0;

        foreach ($servers as $server) {
            $res = $vpnService->syncServerStatuses($server);
            if (!empty($res['success'])) {
                $checked = (int) ($res['checked'] ?? 0);
                $suspNotif = (int) ($res['suspended_notified'] ?? 0);
                $unsuspNotif = (int) ($res['unsuspended_notified'] ?? 0);
                $totalChecked += $checked;
                $totalSuspendedNotified += $suspNotif;
                $totalUnsuspendedNotified += $unsuspNotif;
                $this->info("[{$server->name}] Status disinkronkan ({$checked} akun, {$suspNotif} notif suspend, {$unsuspNotif} notif unsuspend).");
            } else {
                $this->warn("[{$server->name}] Gagal sinkronisasi status: " . ($res['message'] ?? 'Unknown error'));
            }
        }

        $this->line("Selesai. Diperiksa: {$totalChecked}, Notif Suspend: {$totalSuspendedNotified}, Notif Unsuspend: {$totalUnsuspendedNotified}.");
        return self::SUCCESS;
    }
}
