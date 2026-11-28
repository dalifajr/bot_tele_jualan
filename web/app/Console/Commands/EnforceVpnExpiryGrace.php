<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\OrderItem;
use App\Models\VpnAccount;
use App\Notifications\VpnStatusNotification;
use App\Services\TelegramService;
use App\Services\VpnService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnforceVpnExpiryGrace extends Command
{
    protected $signature = 'vpn:enforce-expiry-grace {--dry-run : Tampilkan kandidat tanpa mengeksekusi perubahan}';

    protected $description = 'Enforce 3-day VPN expiry grace period: auto-suspend overdue accounts (0..2 days), permanently delete after 3 days.';

    public function handle(VpnService $vpnService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $graceDays = VpnAccount::EXPIRE_GRACE_DAYS;
        $deleteCutoff = $now->copy()->subDays($graceDays);

        $suspendedCount = 0;
        $deletedCount = 0;

        // Phase 1: Accounts newly overdue (expired_at <= now AND expired_at > now - 3 days) -> Auto-Suspend with grace label
        $toSuspend = VpnAccount::with(['user', 'vpnServer'])
            ->whereIn('status', ['active', 'expired'])
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', $now)
            ->where('expired_at', '>', $deleteCutoff)
            ->get();

        foreach ($toSuspend as $account) {
            $expDate = $account->expired_at->format('Y-m-d');
            $graceUntil = $account->expired_at->copy()->addDays($graceDays)->format('Y-m-d');
            $rawReason = "auto-expired:{$account->protocol}:exp={$expDate}:grace_until={$graceUntil}";

            if ($dryRun) {
                $this->line("[DRY-RUN] Auto-suspend expired grace: {$account->protocol}/{$account->username} (exp={$expDate}, delete_after={$graceUntil})");
                $suspendedCount++;
                continue;
            }

            try {
                $res = $vpnService->suspendAccount($account, 'system', null, $rawReason);
                if (!empty($res['success'])) {
                    $account->refresh();
                    $humanReason = $account->suspended_reason ?: $vpnService->formatSuspendReason($rawReason, true);

                    DB::table('audit_logs')->insert([
                        'action' => 'vpn_expired_auto_suspended',
                        'actor_id' => null,
                        'entity_type' => 'vpn_account',
                        'entity_id' => $account->id,
                        'detail' => AuditLog::maskSensitiveData("username={$account->username}; protocol={$account->protocol}; expired_at={$expDate}; grace_until={$graceUntil}"),
                        'created_at' => now(),
                    ]);

                    try {
                        if ($account->user) {
                            $account->user->notify(new VpnStatusNotification($account, 'suspended', $humanReason, 'system'));
                        }
                        TelegramService::notifyVpnStatusChanged($account, 'suspended', $humanReason, 'system');
                    } catch (\Throwable $ne) {
                        Log::warning('VPN expired-grace suspend notification error: ' . $ne->getMessage());
                    }

                    $suspendedCount++;
                } else {
                    Log::warning('VPN expired-grace suspend failed', [
                        'vpn_account_id' => $account->id,
                        'username' => $account->username,
                        'message' => $res['message'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('VPN expired-grace suspend exception: ' . $e->getMessage(), [
                    'vpn_account_id' => $account->id,
                ]);
            }
        }

        // Phase 2: Accounts past the 3-day grace period (expired_at <= now - 3 days) -> Permanently Delete
        $toDelete = VpnAccount::with(['user', 'vpnServer'])
            ->whereIn('status', ['active', 'suspended', 'expired'])
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', $deleteCutoff)
            ->get();

        foreach ($toDelete as $account) {
            $expDate = $account->expired_at->format('Y-m-d');
            $accountId = $account->id;
            $username = $account->username;
            $protocol = $account->protocol;
            $orderId = $account->order_id;

            if ($dryRun) {
                $this->line("[DRY-RUN] Auto-delete after {$graceDays}d grace: {$protocol}/{$username} (exp={$expDate})");
                $deletedCount++;
                continue;
            }

            try {
                // Stamp originating order items so Order History clearly shows "Akun kadaluarsa & sudah dihapus dari sistem"
                if ($orderId) {
                    $orderItems = OrderItem::where('order_id', $orderId)->get();
                    foreach ($orderItems as $oi) {
                        $matchesUser = !empty($oi->vpn_username) && strtolower((string) $oi->vpn_username) === strtolower($username);
                        $meta = is_array($oi->vpn_meta) ? $oi->vpn_meta : [];
                        $isRenewItem = ($meta['action'] ?? '') === 'renew';
                        if (!$isRenewItem && ($matchesUser || $orderItems->count() === 1)) {
                            $meta['deleted_due_to_expiry'] = true;
                            $meta['deleted_at'] = now()->toDateTimeString();
                            $meta['expired_at'] = $expDate;
                            $meta['deleted_username'] = $username;
                            $meta['deleted_protocol'] = $protocol;
                            $oi->vpn_meta = $meta;
                            $oi->save();
                        }
                    }
                }

                $res = $vpnService->deleteAccount($account, true);
                if (!empty($res['success'])) {
                    DB::table('audit_logs')->insert([
                        'action' => 'vpn_expired_auto_deleted',
                        'actor_id' => null,
                        'entity_type' => 'vpn_account',
                        'entity_id' => $accountId,
                        'detail' => AuditLog::maskSensitiveData("username={$username}; protocol={$protocol}; expired_at={$expDate}; grace_days={$graceDays}"),
                        'created_at' => now(),
                    ]);
                    $deletedCount++;
                } else {
                    Log::warning('VPN expired-grace delete failed', [
                        'vpn_account_id' => $accountId,
                        'username' => $username,
                        'message' => $res['message'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('VPN expired-grace delete exception: ' . $e->getMessage(), [
                    'vpn_account_id' => $accountId,
                ]);
            }
        }

        $this->info("VPN expiry grace enforced: {$suspendedCount} auto-suspended, {$deletedCount} permanently deleted.");

        return self::SUCCESS;
    }
}

