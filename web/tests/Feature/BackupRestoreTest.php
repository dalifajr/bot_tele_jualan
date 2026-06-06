<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $seller;
    protected $customer;

    protected function setUp(): void
    {
        $this->refreshApplication();

        $testDbPath = database_path('test_backup.sqlite');
        
        $dir = dirname($testDbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($testDbPath, '');
        
        config(['database.connections.sqlite.database' => $testDbPath]);

        parent::setUp();

        $this->artisan('migrate');

        $this->admin = User::create([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->seller = User::create([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::create([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $testDbPath = database_path('test_backup.sqlite');
        if (file_exists($testDbPath)) {
            @unlink($testDbPath);
        }
    }

    /**
     * Test guest access control.
     */
    public function test_guest_access_control()
    {
        $routes = [
            route('admin.backup.index'),
            route('admin.backup.restore.show'),
            route('admin.backup.settings.show'),
            route('admin.backup.history'),
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    /**
     * Test customer access control.
     */
    public function test_customer_access_control()
    {
        $this->actingAs($this->customer);
        $routes = [
            route('admin.backup.index'),
            route('admin.backup.restore.show'),
            route('admin.backup.settings.show'),
            route('admin.backup.history'),
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertRedirect(route('dashboard'));
        }
    }

    /**
     * Test seller access control.
     */
    public function test_seller_access_control()
    {
        $this->actingAs($this->seller);
        $routes = [
            route('admin.backup.index'),
            route('admin.backup.restore.show'),
            route('admin.backup.settings.show'),
            route('admin.backup.history'),
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertRedirect(route('dashboard'));
        }
    }

    /**
     * Test admin access control.
     */
    public function test_admin_access_control()
    {
        $this->actingAs($this->admin);
        $routes = [
            route('admin.backup.index'),
            route('admin.backup.restore.show'),
            route('admin.backup.settings.show'),
            route('admin.backup.history'),
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
            $response->assertSee('Backup & Restore');
        }
    }

    /**
     * Test backup generation.
     */
    public function test_backup_generation()
    {
        $this->actingAs($this->admin);

        // Test snapshot generation and download
        $response = $this->get(route('admin.backup.download', 'snapshot'));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');
        
        // Clean generated backups
        $backupHistory = BackupService::getBackupHistory();
        foreach ($backupHistory as $backup) {
            if (File::exists($backup['path'])) {
                File::delete($backup['path']);
            }
        }

        // Test JSON generation and download
        $response = $this->get(route('admin.backup.download', 'json'));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');

        // Clean generated backups
        $backupHistory = BackupService::getBackupHistory();
        foreach ($backupHistory as $backup) {
            if (File::exists($backup['path'])) {
                File::delete($backup['path']);
            }
        }
    }

    /**
     * Test updating auto-backup settings.
     */
    public function test_update_settings()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.backup.settings.update'), [
            'auto_backup_enabled' => '1',
            'auto_backup_schedule' => 'weekly'
        ]);

        $response->assertRedirect();
        
        $this->assertEquals('1', BotSetting::where('key', 'auto_backup_enabled')->value('value'));
        $this->assertEquals('weekly', BotSetting::where('key', 'auto_backup_schedule')->value('value'));
    }

    /**
     * Test auto-backup command execution.
     */
    public function test_auto_backup_command()
    {
        // Set auto backup settings
        BotSetting::create(['key' => 'auto_backup_enabled', 'value' => '1']);
        BotSetting::create(['key' => 'auto_backup_schedule', 'value' => 'daily']);

        // Run the auto backup command
        $exitCode = Artisan::call('app:auto-backup');
        $this->assertEquals(0, $exitCode);

        // Check if last run is logged and audit log is created
        $this->assertNotNull(BotSetting::where('key', 'auto_backup_last_run')->value('value'));
        $this->assertTrue(AuditLog::where('action', 'system_auto_backup')->exists());

        // Clean up generated backup
        $backupHistory = BackupService::getBackupHistory();
        foreach ($backupHistory as $backup) {
            if (File::exists($backup['path'])) {
                File::delete($backup['path']);
            }
        }
    }

    /**
     * Test dynamic SQL dump generation.
     */
    public function test_sql_dump_generation()
    {
        $sql = BackupService::generateSqlDump();
        $this->assertNotEmpty($sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `users`', $sql);
        $this->assertStringContainsString('INSERT INTO `users`', $sql);
    }

    /**
     * Test database restore from valid JSON backup file.
     */
    public function test_restore_from_zip()
    {
        $this->actingAs($this->admin);

        // 1. Create a dummy JSON backup file
        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $tempZipPath = "{$backupDir}/test_restore.zip";
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        // Add dummy manifest
        $manifest = [
            'version' => '1.0',
            'created_at' => now()->toIso8601String(),
            'entity_counts' => [
                'users' => 1
            ]
        ];
        $zip->addFromString('MANIFEST.json', json_encode($manifest));

        // Add dummy users JSON
        $usersData = [
            [
                'username' => 'restored_user',
                'full_name' => 'Restored User',
                'email' => 'restored@test.com',
                'role' => 'customer',
                'password' => bcrypt('password'),
                'telegram_id' => 999999
            ]
        ];
        $zip->addFromString('users.json', json_encode($usersData));
        $zip->close();

        // 2. Call restore route
        $uploadedFile = new UploadedFile(
            $tempZipPath,
            'test_restore.zip',
            'application/zip',
            null,
            true
        );

        $response = $this->post(route('admin.backup.restore'), [
            'backup_file' => $uploadedFile,
            'mode' => 'overwrite'
        ]);

        if (session('error')) {
            dd(session('error'));
        }

        $response->assertRedirect();
        
        // 3. Verify user is restored in database
        $this->assertTrue(User::where('username', 'restored_user')->exists());

        // Clean file
        if (File::exists($tempZipPath)) {
            File::delete($tempZipPath);
        }
    }
}
