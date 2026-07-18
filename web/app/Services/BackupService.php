<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupService
{
    protected static $entities = [
        'users' => User::class,
        'products' => Product::class,
        'stock_units' => StockUnit::class,
        'orders' => Order::class,
        'order_items' => OrderItem::class,
        'payments' => Payment::class,
        'held_funds' => \App\Models\HeldFund::class,
        'restock_subscriptions' => 'restock_subscriptions',
        'complaint_cases' => \App\Models\ComplaintCase::class,
        'complaint_attachments' => 'complaint_attachments',
        'bot_settings' => BotSetting::class,
        'audit_logs' => AuditLog::class,
        'reviews' => \App\Models\Review::class,
        'seller_bank_accounts' => \App\Models\SellerBankAccount::class,
        'vpn_accounts' => \App\Models\VpnAccount::class,
        'product_workers' => 'product_workers',
    ];

    /**
     * Get absolute path of active SQLite database.
     */
    public static function getDatabasePath()
    {
        $dbPath = config('database.connections.sqlite.database');
        
        // Check if path is absolute (starts with / or \ or drive letter like C:)
        if (strpos($dbPath, '/') === 0 || strpos($dbPath, '\\') === 0 || preg_match('/^[a-zA-Z]:/', $dbPath)) {
            return $dbPath;
        }
        
        return base_path($dbPath);
    }

    /**
     * Get list of historical backups stored locally.
     */
    public static function getBackupHistory()
    {
        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            return [];
        }

        $files = File::files($backupDir);
        $history = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'zip') {
                $filename = $file->getFilename();
                $type = strpos($filename, 'json') !== false ? 'JSON (Bot Compatible)' : 'Snapshot (DB + Media)';
                
                $history[] = [
                    'filename' => $filename,
                    'path' => $file->getPathname(),
                    'size' => self::formatBytes($file->getSize()),
                    'raw_size' => $file->getSize(),
                    'created_at' => Carbon::createFromTimestamp($file->getMTime())->format('Y-m-d H:i:s'),
                    'raw_time' => $file->getMTime(),
                    'type' => $type
                ];
            }
        }

        // Sort by newest first
        usort($history, function ($a, $b) {
            return $b['raw_time'] <=> $a['raw_time'];
        });

        return $history;
    }

    /**
     * Delete old backup files, keeping only the latest 10.
     */
    public static function cleanOldBackups()
    {
        $history = self::getBackupHistory();
        if (count($history) > 10) {
            $oldFiles = array_slice($history, 10);
            foreach ($oldFiles as $oldFile) {
                if (File::exists($oldFile['path'])) {
                    File::delete($oldFile['path']);
                }
            }
        }
    }

    /**
     * Generate snapshot backup (Raw SQLite database or SQL dump + media files).
     */
    public static function createSnapshot()
    {
        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $zipName = "backup_snapshot_{$timestamp}.zip";
        $zipPath = "{$backupDir}/{$zipName}";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Could not create ZIP archive at: {$zipPath}");
        }

        $isSqlite = (config('database.default') === 'sqlite');
        $dbSize = 0;

        if ($isSqlite) {
            $dbPath = self::getDatabasePath();
            if (!File::exists($dbPath)) {
                throw new \Exception("Database file not found at: {$dbPath}");
            }
            // Add SQLite Database
            $zip->addFile($dbPath, 'database.sqlite');
            $dbSize = File::size($dbPath);
        } else {
            // Generate SQL dump in pure PHP
            $sqlContent = self::generateSqlDump();
            $zip->addFromString('database.sql', $sqlContent);
            $dbSize = strlen($sqlContent);
        }

        // Add Uploaded Media from public storage
        $publicPath = storage_path('app/public');
        if (File::exists($publicPath)) {
            $files = File::allFiles($publicPath);
            foreach ($files as $file) {
                $relativePath = 'media/' . $file->getRelativePathname();
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }

        // Add bot QRIS if exists
        $botQris = base_path('../src/data/qris.png');
        if (File::exists($botQris)) {
            $zip->addFile($botQris, 'media/bot_qris.png');
        }

        // Add Manifest
        $manifest = [
            'type' => 'snapshot',
            'version' => '1.0',
            'created_at' => Carbon::now()->toIso8601String(),
            'db_type' => config('database.default'),
            'db_size' => $dbSize,
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $zip->close();

        // Keep local backups clean
        self::cleanOldBackups();

        return $zipPath;
    }

    /**
     * Generate structured JSON backup (Fully compatible with Telegram Bot JSON schemas).
     */
    public static function createJsonBackup()
    {
        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $zipName = "backup_json_{$timestamp}.zip";
        $zipPath = "{$backupDir}/{$zipName}";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Could not create ZIP archive at: {$zipPath}");
        }

        $manifestCounts = [];
        $createdAt = Carbon::now()->toIso8601String();

        // 1. Export tables to JSON
        foreach (self::$entities as $entityKey => $modelClass) {
            if (!class_exists($modelClass)) {
                // Handle dynamically if class not found or check if table exists
                $tableName = $entityKey;
            } else {
                $tableName = (new $modelClass)->getTable();
            }

            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $rows = DB::table($tableName)->get();
            $serializedRows = [];

            foreach ($rows as $row) {
                $rowArray = (array) $row;
                // Format datetime values to ISO8601 strings
                foreach ($rowArray as $col => $val) {
                    if ($val && (strpos($col, '_at') !== false || $col === 'expires_at' || $col === 'created_at' || $col === 'updated_at')) {
                        try {
                            $rowArray[$col] = Carbon::parse($val)->toIso8601String();
                        } catch (\Exception $e) {
                            // Leave as string if parsing fails
                        }
                    }
                }
                $serializedRows[] = $rowArray;
            }

            $manifestCounts[$entityKey] = count($serializedRows);

            if (count($serializedRows) > 0) {
                $zip->addFromString("{$entityKey}.json", json_encode($serializedRows, JSON_PRETTY_PRINT));
            }
        }

        // 2. Add MANIFEST.json (Bot compatible)
        $manifest = [
            'version' => '1.0',
            'created_at' => $createdAt,
            'entity_counts' => $manifestCounts,
        ];
        $zip->addFromString('MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $zip->close();

        // Keep local backups clean
        self::cleanOldBackups();

        return $zipPath;
    }

    /**
     * Restore database and media from a uploaded backup ZIP file.
     */
    public static function restore($zipPath, $mode = 'overwrite', ?callable $progressCallback = null)
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath);
        if ($openResult !== true) {
            throw new \Exception("Invalid ZIP file or could not open file. (Error Code: {$openResult})");
        }

        if ($progressCallback) {
            $progressCallback(10, "Mengekstrak berkas ZIP cadangan...");
        }

        // Determine if it is a snapshot or JSON backup
        $hasManifestLower = $zip->locateName('manifest.json') !== false;
        $hasManifestUpper = $zip->locateName('MANIFEST.json') !== false;

        if (!$hasManifestLower && !$hasManifestUpper) {
            $zip->close();
            throw new \Exception("Missing manifest file (MANIFEST.json / manifest.json) in backup archive.");
        }

        if ($hasManifestLower) {
            // Snapshot restore
            $manifestContent = $zip->getFromName('manifest.json');
            $manifest = json_decode($manifestContent, true);
            
            if (($manifest['type'] ?? '') !== 'snapshot') {
                $zip->close();
                throw new \Exception("Invalid manifest configuration for snapshot restore.");
            }

            if ($progressCallback) {
                $progressCallback(20, "Memulai restore snapshot database...");
            }
            self::restoreSnapshot($zip, $progressCallback);
        } else {
            // JSON restore
            $manifestContent = $zip->getFromName('MANIFEST.json');
            $manifest = json_decode($manifestContent, true);
            if ($progressCallback) {
                $progressCallback(20, "Memulai restore data JSON...");
            }
            self::restoreJson($zip, $manifest, $mode, $progressCallback);
        }

        // Ensure critical system tables exist after restore
        self::ensureSystemTablesExist();

        $zip->close();
        if ($progressCallback) {
            $progressCallback(100, "Proses restore selesai dengan sukses!");
        }
        return true;
    }

    /**
     * Restore snapshot raw SQLite & media files.
     */
    protected static function restoreSnapshot(ZipArchive $zip, ?callable $progressCallback = null)
    {
        $dbContent = $zip->getFromName('database.sqlite');
        
        if (!$dbContent) {
            // Fallback: Check if database.sql exists
            $sqlContent = $zip->getFromName('database.sql');
            if ($sqlContent) {
                if ($progressCallback) {
                    $progressCallback(40, "Menjalankan SQL Dump restore...");
                }
                self::restoreSqlDump($sqlContent);
                if ($progressCallback) {
                    $progressCallback(70, "Memulihkan berkas media dari ZIP...");
                }
                self::restoreMediaFromZip($zip);
                return;
            }
            throw new \Exception("database.sqlite or database.sql file is missing in the snapshot ZIP.");
        }

        // Write to temporary SQLite database file
        if ($progressCallback) {
            $progressCallback(30, "Menulis database sementara...");
        }
        $tempDb = tempnam(sys_get_temp_dir(), 'sqlite_restore');
        File::put($tempDb, $dbContent);

        // Extract media files
        if ($progressCallback) {
            $progressCallback(60, "Memulihkan berkas media dari ZIP...");
        }
        self::restoreMediaFromZip($zip);

        // Overwrite active database file safely
        if ($progressCallback) {
            $progressCallback(80, "Menimpa database aktif dengan snapshot...");
        }
        $activeDb = self::getDatabasePath();
        
        // Temporarily close DB connection
        DB::disconnect();

        try {
            // Backup active database in case of copy failures
            $rollbackDb = $activeDb . '.rollback';
            if (File::exists($activeDb)) {
                File::copy($activeDb, $rollbackDb);
            }

            if (!File::copy($tempDb, $activeDb)) {
                if (File::exists($rollbackDb)) {
                    File::copy($rollbackDb, $activeDb);
                    File::delete($rollbackDb);
                }
                throw new \Exception("Failed to copy snapshot database to active path.");
            }

            if (File::exists($rollbackDb)) {
                File::delete($rollbackDb);
            }
        } finally {
            File::delete($tempDb);
            // Reconnect DB
            DB::reconnect();
        }
    }

    /**
     * Restore tables using exported JSON records.
     */
    protected static function restoreJson(ZipArchive $zip, $manifest, $mode, ?callable $progressCallback = null)
    {
        $entitiesData = [];

        if ($progressCallback) {
            $progressCallback(30, "Mengekstrak data entitas dari ZIP...");
        }

        // Load all data from ZIP
        foreach (self::$entities as $entityKey => $modelClass) {
            $jsonFile = "{$entityKey}.json";
            if ($zip->locateName($jsonFile) !== false) {
                $content = $zip->getFromName($jsonFile);
                $entitiesData[$entityKey] = json_decode($content, true) ?: [];
            } else {
                $entitiesData[$entityKey] = [];
            }
        }

        if ($progressCallback) {
            $progressCallback(50, "Memulai transaksi database dan menonaktifkan foreign keys...");
        }

        // Run tables import in transaction to ensure consistency
        DB::transaction(function () use ($entitiesData, $mode, $progressCallback) {
            // Disable foreign key constraints temporarily to allow truncates/updates
            Schema::disableForeignKeyConstraints();

            try {
                if ($mode === 'overwrite') {
                    // Truncate tables in reverse dependency order
                    $tablesToClear = array_reverse(array_keys(self::$entities));
                    foreach ($tablesToClear as $index => $entityKey) {
                        $modelClass = self::$entities[$entityKey];
                        if (class_exists($modelClass)) {
                            $tableName = (new $modelClass)->getTable();
                            if (Schema::hasTable($tableName)) {
                                if ($progressCallback) {
                                    $progressCallback(55 + $index, "Mengosongkan tabel: {$tableName}...");
                                }
                                DB::table($tableName)->delete();
                            }
                        }
                    }
                }

                // Insert entity records in dependency order
                $totalEntities = count(self::$entities);
                $index = 0;
                foreach (self::$entities as $entityKey => $modelClass) {
                    $index++;
                    if (!class_exists($modelClass)) continue;

                    $tableName = (new $modelClass)->getTable();
                    if (!Schema::hasTable($tableName)) continue;

                    $rows = $entitiesData[$entityKey] ?? [];
                    if (empty($rows)) continue;

                    $percent = 70 + (int) (($index / $totalEntities) * 25);
                    if ($progressCallback) {
                        $progressCallback($percent, "Mengimpor data ke tabel: {$tableName} (" . count($rows) . " baris)...");
                    }

                    foreach ($rows as $row) {
                        $primaryKey = (new $modelClass)->getKeyName() ?: 'id';
                        $primaryVal = $row[$primaryKey] ?? null;

                        // Check for duplicate record
                        $exists = false;
                        if ($mode === 'merge' && $primaryVal) {
                            $exists = DB::table($tableName)->where($primaryKey, $primaryVal)->exists();
                        }

                        if ($mode === 'merge' && !$exists) {
                            // Contextual duplicate checks
                            if ($entityKey === 'users' && isset($row['telegram_id'])) {
                                $exists = DB::table($tableName)->where('telegram_id', $row['telegram_id'])->exists();
                            } elseif ($entityKey === 'products' && isset($row['name'])) {
                                $exists = DB::table($tableName)->where('name', $row['name'])->exists();
                            } elseif ($entityKey === 'orders' && isset($row['order_ref'])) {
                                $exists = DB::table($tableName)->where('order_ref', $row['order_ref'])->exists();
                            }
                        }

                        if ($exists && $mode === 'merge') {
                            // Update row
                            if ($primaryVal) {
                                DB::table($tableName)->where($primaryKey, $primaryVal)->update($row);
                            }
                        } else {
                            // Insert row
                            DB::table($tableName)->insert($row);
                        }
                    }
                }
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });
    }

    /**
     * Helper to format file sizes.
     */
    protected static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Generate SQL dump of tables dynamically in pure PHP.
     */
    public static function generateSqlDump()
    {
        $sql = "";
        $tables = [];

        try {
            if (method_exists(Schema::class, 'getTables')) {
                $tableInfos = Schema::getTables();
                foreach ($tableInfos as $info) {
                    $name = is_array($info) ? ($info['name'] ?? null) : ($info->name ?? null);
                    if ($name && !str_starts_with($name, 'sqlite_')) {
                        $tables[] = $name;
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore schema reading errors
        }

        if (empty($tables)) {
            $tables = array_keys(self::$entities);
        }

        $connection = config('database.default');

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

            if ($connection === 'mysql') {
                try {
                    $result = DB::select("SHOW CREATE TABLE `{$table}`");
                    if (!empty($result)) {
                        $createTableSql = ((array)$result[0])['Create Table'] ?? '';
                        $sql .= $createTableSql . ";\n\n";
                    }
                } catch (\Exception $e) {
                }
            } elseif ($connection === 'sqlite') {
                try {
                    $result = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'");
                    if (!empty($result)) {
                        $createTableSql = $result[0]->sql ?? '';
                        $sql .= $createTableSql . ";\n\n";
                    }
                } catch (\Exception $e) {
                }
            }

            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $rowArray = (array)$row;
                if (empty($rowArray)) continue;

                $columns = array_map(function($col) {
                    return "`{$col}`";
                }, array_keys($rowArray));

                $values = array_map(function($val) {
                    if ($val === null) {
                        return 'NULL';
                    }
                    return DB::getPdo()->quote($val);
                }, array_values($rowArray));

                $sql .= "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
            $sql .= "\n";
        }

        return $sql;
    }

    /**
     * Restore database from raw SQL content.
     */
    protected static function restoreSqlDump($sql)
    {
        Schema::disableForeignKeyConstraints();
        try {
            // Drop all known tables in reverse dependency order first if defined in the SQL dump
            $tables = array_reverse(array_keys(self::$entities));
            foreach ($tables as $table) {
                if (self::sqlContainsTable($sql, $table)) {
                    Schema::dropIfExists($table);
                }
            }

            // Drop sessions, cache, and other tables if they exist and are defined in the SQL dump
            $extraTables = [
                'sessions', 'cache', 'cache_locks', 'visitors', 
                'coupons', 'withdrawal_requests', 'chat_messages', 
                'cart_items', 'broadcast_jobs', 'coupon_user',
                'broadcast_logs', 'github_check_batches', 'github_check_results',
                'listener_events', 'notification_retry_jobs', 'notifications',
                'telegram_link_tokens', 'telegram_login_tokens', 'login_logs',
                'telemetry_events', 'update_history'
            ];
            foreach ($extraTables as $table) {
                if (self::sqlContainsTable($sql, $table)) {
                    Schema::dropIfExists($table);
                }
            }

            DB::unprepared($sql);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Check if a table definition exists in the SQL string.
     */
    protected static function sqlContainsTable($sql, $table)
    {
        $pattern = '/(?:CREATE\s+TABLE|DROP\s+TABLE(?:\s+IF\s+EXISTS)?|INSERT\s+INTO)\s+[`"]?' . preg_quote($table, '/') . '[`"]?/i';
        return (bool) preg_match($pattern, $sql);
    }

    /**
     * Ensure crucial framework tables exist after restore.
     */
    public static function ensureSystemTablesExist()
    {
        if (!Schema::hasTable('sessions')) {
            try {
                Schema::create('sessions', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->string('id')->primary();
                    $table->foreignId('user_id')->nullable()->index();
                    $table->string('ip_address', 45)->nullable();
                    $table->text('user_agent')->nullable();
                    $table->longText('payload');
                    $table->integer('last_activity')->index();
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing sessions table: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('cache')) {
            try {
                Schema::create('cache', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->string('key')->primary();
                    $table->mediumText('value');
                    $table->bigInteger('expiration')->index();
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing cache table: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('cache_locks')) {
            try {
                Schema::create('cache_locks', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->string('key')->primary();
                    $table->string('owner');
                    $table->bigInteger('expiration')->index();
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing cache_locks table: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('visitors')) {
            try {
                Schema::create('visitors', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->string('ip_address', 45);
                    $table->date('visited_date');
                    $table->timestamps();
                    $table->unique(['ip_address', 'visited_date']);
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing visitors table: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('coupons')) {
            try {
                Schema::create('coupons', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->string('code', 64)->unique();
                    $table->string('type', 32)->default('fixed');
                    $table->integer('value');
                    $table->integer('min_spend')->default(0);
                    $table->integer('max_discount')->nullable();
                    $table->integer('qty')->default(0);
                    $table->integer('used_qty')->default(0);
                    $table->timestamp('expires_at')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing coupons table: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('coupon_user')) {
            try {
                Schema::create('coupon_user', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('coupon_id');
                    $table->unsignedBigInteger('user_id');
                    $table->timestamp('created_at')->useCurrent();
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing coupon_user table: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('cart_items')) {
            try {
                Schema::create('cart_items', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('user_id');
                    $table->unsignedBigInteger('product_id');
                    $table->integer('quantity')->default(1);
                    $table->timestamps();
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing cart_items table: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('chat_messages')) {
            try {
                Schema::create('chat_messages', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('sender_id');
                    $table->unsignedBigInteger('receiver_id');
                    $table->text('message');
                    $table->boolean('is_read')->default(false);
                    $table->timestamps();
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing chat_messages table: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('broadcast_jobs')) {
            try {
                Schema::create('broadcast_jobs', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->text('message');
                    $table->integer('total_targets')->default(0);
                    $table->integer('sent_count')->default(0);
                    $table->integer('failed_count')->default(0);
                    $table->string('status', 32)->default('pending');
                    $table->unsignedBigInteger('admin_id')->nullable();
                    $table->boolean('is_read')->default(false);
                    $table->timestamps();
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing broadcast_jobs table: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('withdrawal_requests')) {
            try {
                Schema::create('withdrawal_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('seller_id');
                    $table->integer('amount');
                    $table->string('bank_name', 100);
                    $table->string('account_number', 100);
                    $table->string('account_holder', 255);
                    $table->string('status', 32)->default('pending');
                    $table->text('rejection_reason')->nullable();
                    $table->string('proof_image_path', 255)->nullable();
                    $table->timestamp('created_at')->useCurrent();
                    $table->timestamp('processed_at')->nullable();
                });
            } catch (\Exception $e) {
                Log::warning("Failed to create missing withdrawal_requests table: " . $e->getMessage());
            }
        }
    }

    /**
     * Restore media files from ZIP backup.
     */
    protected static function restoreMediaFromZip(ZipArchive $zip)
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, 'media/') === 0) {
                $relativePath = substr($filename, 6); // Remove 'media/'
                if (empty($relativePath)) continue;

                $fileData = $zip->getFromIndex($i);
                $destPath = storage_path("app/public/{$relativePath}");
                
                File::ensureDirectoryExists(dirname($destPath));
                File::put($destPath, $fileData);

                if ($relativePath === 'bot_qris.png' || $relativePath === 'qris/qris_latest.png') {
                    $botQris = base_path('../src/data/qris.png');
                    File::ensureDirectoryExists(dirname($botQris));
                    File::put($botQris, $fileData);
                }
            }
        }
    }
}
