<?php

namespace App\Console\Commands;

use App\Models\LoginLog;
use Illuminate\Console\Command;

class PruneDuplicateLoginLogs extends Command
{
    protected $signature = 'logins:prune-duplicates
                            {--window=60 : Jendela deduplikasi dalam detik untuk login sukses identik}
                            {--dry-run : Hanya tampilkan jumlah baris yang akan dihapus tanpa menghapus}';

    protected $description = 'Membersihkan baris spam duplikat login sukses identik (mis. akibat reload loop iframe) tanpa menyentuh log login gagal.';

    public function handle(): int
    {
        $window = max(5, (int) $this->option('window'));
        $dryRun = (bool) $this->option('dry-run');

        $lastKeptByKey = [];
        $idsToDelete = [];

        LoginLog::query()
            ->where('is_successful', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$lastKeptByKey, &$idsToDelete, $window) {
                foreach ($rows as $row) {
                    $actorKey = $row->user_id ? ('uid:' . $row->user_id) : ('u:' . strtolower((string) $row->username_or_email));
                    $key = $actorKey . '|' . (string) $row->ip_address . '|' . (string) $row->browser;
                    $ts = $row->created_at ? $row->created_at->getTimestamp() : 0;

                    if (isset($lastKeptByKey[$key]) && abs($ts - $lastKeptByKey[$key]) <= $window) {
                        $idsToDelete[] = $row->id;
                    } else {
                        $lastKeptByKey[$key] = $ts;
                    }
                }
            });

        $totalDupes = count($idsToDelete);
        if ($totalDupes === 0) {
            $this->info('Tidak ditemukan baris duplikat login sukses untuk dibersihkan.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[DRY-RUN] Ditemukan {$totalDupes} baris duplikat login sukses (jendela {$window} detik).");
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach (array_chunk($idsToDelete, 500) as $chunk) {
            $deleted += LoginLog::whereIn('id', $chunk)->delete();
        }

        $this->info("Berhasil membersihkan {$deleted} baris duplikat login sukses (log login gagal tidak disentuh).");
        return self::SUCCESS;
    }
}
