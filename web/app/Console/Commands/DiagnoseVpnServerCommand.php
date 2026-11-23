<?php

namespace App\Console\Commands;

use App\Models\VpnServer;
use App\Services\VpnService;
use Illuminate\Console\Command;

class DiagnoseVpnServerCommand extends Command
{
    protected $signature = 'vpn:diagnose {server? : Server ID or unique code (omit to diagnose all active servers)}';
    protected $description = 'Run live 5-stage connection & authentication diagnostics against configured VPN panel servers';

    public function handle(VpnService $vpnService): int
    {
        $target = $this->argument('server');
        $query = VpnServer::query()->orderByDesc('is_default')->orderBy('id');

        if ($target !== null && $target !== '') {
            $query->where(function ($q) use ($target) {
                if (ctype_digit((string) $target)) {
                    $q->where('id', (int) $target);
                }
                $q->orWhere('code', strtolower((string) $target));
            });
        }

        $servers = $query->get();
        if ($servers->isEmpty()) {
            $this->error('Tidak ada server VPN yang ditemukan di tabel vpn_servers.');
            return self::FAILURE;
        }

        $anyFailed = false;

        foreach ($servers as $server) {
            $this->line('');
            $this->info("=== Diagnostik Server VPN: {$server->name} [{$server->code}] ({$server->driver}) ===");
            $this->line("Endpoint: {$server->display_endpoint}");

            $report = $vpnService->diagnoseServer($server);

            foreach ($report['steps'] as $step) {
                $tag = match ($step['status']) {
                    'ok' => '<fg=green>[OK]</>',
                    'warn' => '<fg=yellow>[WARN]</>',
                    default => '<fg=red>[FAIL]</>',
                };
                $ms = isset($step['latency_ms']) && $step['latency_ms'] !== null ? " ({$step['latency_ms']} ms)" : '';
                $this->line(" {$tag} {$step['title']}{$ms}");
                $this->line("       {$step['detail']}");
                if (!empty($step['hint'])) {
                    $this->line("       <fg=yellow>Saran:</> {$step['hint']}");
                }
            }

            if (!empty($report['ok'])) {
                $this->info("HASIL: ONLINE — {$report['headline']}");
            } else {
                $anyFailed = true;
                $this->error("HASIL: OFFLINE — {$report['headline']}");
            }
        }

        return $anyFailed ? self::FAILURE : self::SUCCESS;
    }
}
