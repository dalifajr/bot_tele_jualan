<?php

namespace Tests\Feature;

use App\Models\BroadcastFailure;
use App\Models\BroadcastJob;
use App\Models\User;
use App\Services\BroadcastDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BroadcastFailureDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'username' => 'admin_bc_fail',
            'full_name' => 'Admin Diagnostik',
            'email' => 'admin_bc_fail@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_broadcast_records_classified_telegram_errors_and_serves_analytics_and_csv_export(): void
    {
        config(['telegram.bot_token' => 'mock_token_diag']);

        $users = [
            User::forceCreate([
                'username' => 'user_ok',
                'full_name' => 'User Berhasil',
                'email' => 'ok@test.com',
                'role' => 'customer',
                'telegram_id' => 900001,
                'password' => bcrypt('password'),
            ]),
            User::forceCreate([
                'username' => 'user_blocked',
                'full_name' => 'User Blokir',
                'email' => 'blocked@test.com',
                'role' => 'customer',
                'telegram_id' => 900002,
                'password' => bcrypt('password'),
            ]),
            User::forceCreate([
                'username' => 'user_nochat',
                'full_name' => 'User Belum Start',
                'email' => 'nochat@test.com',
                'role' => 'customer',
                'telegram_id' => 900003,
                'password' => bcrypt('password'),
            ]),
            User::forceCreate([
                'username' => 'user_badhtml',
                'full_name' => 'User HTML Error',
                'email' => 'badhtml@test.com',
                'role' => 'customer',
                'telegram_id' => 900004,
                'password' => bcrypt('password'),
            ]),
            User::forceCreate([
                'username' => 'user_deleted',
                'full_name' => 'User Dihapus',
                'email' => 'deleted@test.com',
                'role' => 'customer',
                'telegram_id' => 900005,
                'password' => bcrypt('password'),
            ]),
        ];

        Http::fake(function ($request) {
            $chatId = (string) ($request->data()['chat_id'] ?? '');

            return match ($chatId) {
                '900001' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
                '900002' => Http::response([
                    'ok' => false,
                    'error_code' => 403,
                    'description' => 'Forbidden: bot was blocked by the user',
                ], 403),
                '900003' => Http::response([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => 'Bad Request: chat not found',
                ], 400),
                '900004' => Http::response([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: Can't find end tag corresponding to start tag \"b\"",
                ], 400),
                '900005' => Http::response([
                    'ok' => false,
                    'error_code' => 403,
                    'description' => 'Forbidden: user is deactivated',
                ], 403),
                default => Http::response(['ok' => false, 'description' => 'Unexpected chat'], 500),
            };
        });

        $job = BroadcastJob::create([
            'message' => '<b>Promo Spesial</b>',
            'total_targets' => 5,
            'web_target_count' => 5,
            'status' => 'pending',
            'admin_id' => $this->admin->id,
        ]);

        $this->artisan('broadcast:run', ['jobId' => $job->id])->assertExitCode(0);

        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertEquals(1, $job->sent_count);
        $this->assertEquals(4, $job->failed_count);
        $this->assertEquals(4, BroadcastFailure::where('broadcast_job_id', $job->id)->count());

        $this->assertDatabaseHas('broadcast_failures', [
            'broadcast_job_id' => $job->id,
            'user_id' => $users[1]->id,
            'telegram_id' => '900002',
            'recipient_name' => 'User Blokir (@user_blocked)',
            'error_code' => 403,
            'error_category' => 'bot_blocked',
            'error_message' => 'Forbidden: bot was blocked by the user',
        ]);

        $this->assertDatabaseHas('broadcast_failures', [
            'broadcast_job_id' => $job->id,
            'telegram_id' => '900003',
            'error_code' => 400,
            'error_category' => 'chat_not_found',
        ]);

        $this->assertDatabaseHas('broadcast_failures', [
            'broadcast_job_id' => $job->id,
            'telegram_id' => '900004',
            'error_code' => 400,
            'error_category' => 'invalid_html',
        ]);

        $this->assertDatabaseHas('broadcast_failures', [
            'broadcast_job_id' => $job->id,
            'telegram_id' => '900005',
            'error_code' => 403,
            'error_category' => 'user_deactivated',
        ]);

        // Analytics API returns failure_summary & detailed failures list
        $analyticsRes = $this->actingAs($this->admin)
            ->getJson(route('admin.broadcast.analytics', $job->id));

        $analyticsRes->assertStatus(200);
        $analyticsRes->assertJsonPath('analytics.failed_count', 4);
        $analyticsRes->assertJsonPath('analytics.failures_total_logged', 4);
        $this->assertCount(4, $analyticsRes->json('analytics.failure_summary'));
        $this->assertCount(4, $analyticsRes->json('analytics.failures'));

        $categories = collect($analyticsRes->json('analytics.failure_summary'))->pluck('category')->all();
        $this->assertEqualsCanonicalizing(
            ['bot_blocked', 'chat_not_found', 'invalid_html', 'user_deactivated'],
            $categories
        );

        // CSV Export (all failures)
        $csvAll = $this->actingAs($this->admin)
            ->get(route('admin.broadcast.failures.export', $job->id));
        $csvAll->assertStatus(200);
        $csvAll->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $contentAll = $csvAll->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contentAll);
        $this->assertStringContainsString('900002', $contentAll);
        $this->assertStringContainsString('900003', $contentAll);
        $this->assertStringContainsString('Forbidden: bot was blocked by the user', $contentAll);

        // CSV Export filtered by category
        $csvFiltered = $this->actingAs($this->admin)
            ->get(route('admin.broadcast.failures.export', ['jobId' => $job->id, 'category' => 'bot_blocked']));
        $csvFiltered->assertStatus(200);
        $contentFiltered = $csvFiltered->streamedContent();
        $this->assertStringContainsString('900002', $contentFiltered);
        $this->assertStringNotContainsString('900003', $contentFiltered);
    }

    public function test_classifier_handles_media_rate_limit_and_network_errors(): void
    {
        $service = app(BroadcastDeliveryService::class);

        $this->assertEquals('media_error', $service->classifyTelegramError(413, 'Request Entity Too Large'));
        $this->assertEquals('media_error', $service->classifyTelegramError(400, 'Bad Request: file is too big'));
        $this->assertEquals('rate_limited', $service->classifyTelegramError(429, 'Too Many Requests: retry after 10'));
        $this->assertEquals('network_error', $service->classifyTelegramError(null, 'cURL error 28: Operation timed out'));
        $this->assertEquals('other', $service->classifyTelegramError(500, 'Internal server failure'));
    }
}
