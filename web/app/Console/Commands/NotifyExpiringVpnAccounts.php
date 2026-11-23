<?php

namespace App\Console\Commands;

use App\Models\VpnAccount;
use App\Notifications\VpnExpiringNotification;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class NotifyExpiringVpnAccounts extends Command
{
    protected $signature = 'vpn:notify-expiring';
    protected $description = 'Kirim notifikasi Telegram (tombol Renew Sekarang) dan Notification Hub website untuk akun VPN yang hampir expired (H-3 & H-1)';

    public function handle(): int
    {
        $now = now();
        $horizon = $now->copy()->addHours(72);

        $accounts = VpnAccount::with(['user', 'vpnServer'])
            ->where('status', 'active')
            ->whereNotNull('expired_at')
            ->where('expired_at', '>', $now)
            ->where('expired_at', '<=', $horizon)
            ->get();

        $sentCount = 0;

        foreach ($accounts as $account) {
            if (!$account->user) {
                continue;
            }

            $hoursLeft = $now->diffInMinutes($account->expired_at) / 60;
            $stage = $hoursLeft <= 24 ? '1d' : '3d';

            // Anti-spam: do not re-send same or lower urgency stage
            if ($account->expiry_notified_stage === $stage) {
                continue;
            }
            if ($stage === '3d' && $account->expiry_notified_stage === '1d') {
                continue;
            }

            // 1. Website Notification Hub (always delivered even without telegram_id)
            try {
                $account->user->notify(new VpnExpiringNotification($account, $stage));
            } catch (\Throwable $e) {
                $this->warn("Gagal kirim Notification Hub untuk {$account->username}: " . $e->getMessage());
            }

            // 2. Telegram Bot Notification with inline "Renew Sekarang" button
            TelegramService::notifyVpnExpiring($account, $stage);

            $account->forceFill([
                'expiry_notified_stage' => $stage,
                'expiry_notified_at' => now(),
            ])->save();

            $sentCount++;
        }

        $this->info("Selesai mengirim pengingat hampir expired untuk {$sentCount} akun VPN.");
        return self::SUCCESS;
    }
}
