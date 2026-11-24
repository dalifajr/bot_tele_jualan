<?php

namespace Tests\Feature;

use App\Http\Middleware\ConfigureWebAppSessionCookie;
use App\Http\Middleware\ResolveCloudflareRealIp;
use App\Models\LoginLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TelegramWebAppLoopAndCloudflareIpTest extends TestCase
{
    use RefreshDatabase;

    private function buildTelegramInitData(string $botToken, array $userPayload): string
    {
        $params = [
            'auth_date' => (string) time(),
            'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
            'user' => json_encode($userPayload),
        ];
        ksort($params);
        $dataCheckArr = [];
        foreach ($params as $k => $v) {
            $dataCheckArr[] = "{$k}={$v}";
        }
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $params['hash'] = hash_hmac('sha256', implode("\n", $dataCheckArr), $secretKey);

        return http_build_query($params);
    }

    public function test_webapp_https_sets_samesite_none_secure_partitioned_cookie_while_normal_stays_lax(): void
    {
        $mw = new ConfigureWebAppSessionCookie();

        // 1. Regular HTTP / non-iframe request keeps SameSite=lax
        config(['session.same_site' => 'lax', 'session.secure' => false, 'session.partitioned' => false]);
        $normalReq = Request::create('/login', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $mw->handle($normalReq, fn () => new Response('ok'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertFalse((bool) config('session.partitioned'));

        // 2. HTTPS Telegram WebApp / iframe request upgrades cookie to SameSite=none + Secure + Partitioned
        $iframeReq = Request::create(
            'https://belajaridn.id/auth/telegram/webapp',
            'POST',
            [],
            [],
            [],
            [
                'HTTPS' => 'on',
                'HTTP_SEC_FETCH_DEST' => 'iframe',
                'HTTP_REFERER' => 'https://web.telegram.org/',
            ]
        );
        $mw->handle($iframeReq, fn () => new Response('ok'));
        $this->assertSame('none', config('session.same_site'));
        $this->assertTrue((bool) config('session.secure'));
        $this->assertTrue((bool) config('session.partitioned'));
    }

    public function test_cloudflare_real_ip_is_resolved_only_for_trusted_cloudflare_cidrs_and_blocks_spoofing(): void
    {
        $mw = new ResolveCloudflareRealIp();

        // 1. Request coming from official Cloudflare CIDR (172.71.152.85 is in 172.64.0.0/13) trusts CF-Connecting-IP
        $cfReq = Request::create('/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '172.71.152.85',
            'HTTP_CF_CONNECTING_IP' => '140.213.168.234',
        ]);
        $mw->handle($cfReq, fn () => new Response('ok'));
        $this->assertSame('140.213.168.234', $cfReq->ip());

        // 2. Direct spoofed request from arbitrary non-Cloudflare IP ignores CF-Connecting-IP!
        $spoofReq = Request::create('/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.50',
            'HTTP_CF_CONNECTING_IP' => '1.2.3.4',
        ]);
        $mw->handle($spoofReq, fn () => new Response('ok'));
        $this->assertSame('203.0.113.50', $spoofReq->ip());
    }

    public function test_webapp_login_is_idempotent_deduplicates_login_logs_and_throttles_bursts(): void
    {
        $botToken = '123456:ABC-DEF-TEST-TOKEN';
        config(['telegram.bot_token' => $botToken]);

        $user = User::create([
            'username' => 'rinvietations',
            'full_name' => 'Rinvietations',
            'email' => 'rin@test.local',
            'telegram_id' => '555111222',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $initData = $this->buildTelegramInitData($botToken, [
            'id' => 555111222,
            'username' => 'rinvietations',
            'first_name' => 'Rinvietations',
        ]);

        // Hit /auth/telegram/webapp 5 times rapidly (simulating iframe reload storm) via Cloudflare IP
        for ($i = 0; $i < 5; $i++) {
            $res = $this->withServerVariables([
                'REMOTE_ADDR' => '172.71.152.85',
                'HTTP_CF_CONNECTING_IP' => '140.213.168.234',
            ])->postJson('/auth/telegram/webapp', ['init_data' => $initData]);

            $res->assertStatus(200)->assertJsonPath('success', true);
            if ($i > 0) {
                $res->assertJsonPath('already_authenticated', true);
            }
        }

        // Only ONE LoginLog row created, with REAL client IP (140.213.168.234), NOT Cloudflare IP!
        $this->assertSame(1, LoginLog::where('user_id', $user->id)->where('is_successful', true)->count());
        $log = LoginLog::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('140.213.168.234', $log->ip_address);

        // Even if session is cleared (simulating blocked cookie), LogsLogin deduplication STILL prevents spam rows within 60s!
        auth()->logout();
        $this->withServerVariables([
            'REMOTE_ADDR' => '172.71.152.85',
            'HTTP_CF_CONNECTING_IP' => '140.213.168.234',
        ])->postJson('/auth/telegram/webapp', ['init_data' => $initData])->assertStatus(200);

        $this->assertSame(1, LoginLog::where('user_id', $user->id)->where('is_successful', true)->count());

        // Continued hammering hits throttle:12,1 (429 Too Many Requests)
        $hit429 = false;
        for ($i = 0; $i < 15; $i++) {
            $r = $this->withServerVariables([
                'REMOTE_ADDR' => '172.71.152.85',
                'HTTP_CF_CONNECTING_IP' => '140.213.168.234',
            ])->postJson('/auth/telegram/webapp', ['init_data' => $initData]);
            if ($r->getStatusCode() === 429) {
                $hit429 = true;
                break;
            }
        }
        $this->assertTrue($hit429, 'Expected /auth/telegram/webapp to enforce throttle:12,1');
    }

    public function test_prune_duplicate_login_logs_command_keeps_normal_and_failed_logs(): void
    {
        $user = User::create([
            'username' => 'spam_user',
            'full_name' => 'Spam User',
            'email' => 'spam@test.local',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $baseTime = now()->subMinutes(10);
        // Insert 5 duplicate successful rows in same minute
        for ($i = 0; $i < 5; $i++) {
            $row = LoginLog::create([
                'user_id' => $user->id,
                'ip_address' => '172.71.152.85',
                'username_or_email' => 'spam_user',
                'is_successful' => true,
                'browser' => 'Telegram Browser',
                'device_type' => 'Desktop',
            ]);
            $row->created_at = $baseTime->copy()->addSeconds($i * 2);
            $row->save();
        }

        // Insert 2 failed rows in same minute (MUST NOT be pruned!)
        for ($i = 0; $i < 2; $i++) {
            $row = LoginLog::create([
                'user_id' => $user->id,
                'ip_address' => '172.71.152.85',
                'username_or_email' => 'spam_user',
                'is_successful' => false,
                'browser' => 'Chrome',
                'device_type' => 'Desktop',
            ]);
            $row->created_at = $baseTime->copy()->addSeconds($i);
            $row->save();
        }

        $this->artisan('logins:prune-duplicates', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame(7, LoginLog::count());

        $this->artisan('logins:prune-duplicates')->assertExitCode(0);
        // 1 kept successful + 2 kept failed = 3 rows total
        $this->assertSame(1, LoginLog::where('is_successful', true)->count());
        $this->assertSame(2, LoginLog::where('is_successful', false)->count());
    }

    public function test_frontend_views_render_webapp_loop_guard(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('tg_webapp_auth_guard')
            ->assertSee('credentials: \'same-origin\'', false);
    }

    public function test_admin_stock_replacement_single_and_bulk_marks_unit_and_notifies_hub_and_telegram_miniapp(): void
    {
        config([
            'services.telegram.bot_token' => 'test-bot-token-replace',
            'app.url' => 'https://belajaridn.id',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 202]], 200),
        ]);

        $admin = User::create([
            'username' => 'admin_rep',
            'full_name' => 'Admin Replace',
            'email' => 'adminrep@test.local',
            'password' => bcrypt('secret123'),
        ]);
        $admin->role = 'admin';
        $admin->save();

        $customer = User::create([
            'username' => 'buyer_rep',
            'full_name' => 'Buyer Replace',
            'email' => 'buyerrep@test.local',
            'telegram_id' => '999888777',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Akun GitHub Pro',
            'price' => 30000,
            'is_suspended' => false,
            'is_vpn' => false,
            'creator_id' => $admin->id,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-REP-001',
            'customer_id' => $customer->id,
            'subtotal' => 60000,
            'discount_amount' => 0,
            'unique_code' => 15,
            'total_amount' => 60015,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 30000,
        ]);

        $soldUnit1 = StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $admin->id,
            'raw_text' => "Username: old_user_1\nPass: oldpass1",
            'stock_status' => 'ready',
            'is_sold' => true,
            'sold_order_id' => $order->id,
        ]);
        $soldUnit2 = StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $admin->id,
            'raw_text' => "Username: old_user_2\nPass: oldpass2",
            'stock_status' => 'ready',
            'is_sold' => true,
            'sold_order_id' => $order->id,
        ]);
        $replacement1 = StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $admin->id,
            'raw_text' => "Username: fresh_user_1\nPass: freshpass1",
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);
        $replacement2 = StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $admin->id,
            'raw_text' => "Username: fresh_user_2\nPass: freshpass2",
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);

        // 1. Single replaceStock
        $this->actingAs($admin)
            ->post(route('admin.orders.replace-stock', [$order->id, $soldUnit1->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $replacement1->refresh();
        $soldUnit1->refresh();
        $this->assertTrue((bool) $replacement1->is_replaced);
        $this->assertNotNull($replacement1->replaced_at);
        $this->assertSame($soldUnit1->id, (int) $replacement1->replaced_from_stock_id);
        $this->assertFalse((bool) $soldUnit1->is_replaced);

        // 2. Bulk replaceStockBulk
        $this->actingAs($admin)
            ->post(route('admin.orders.replace-stock-bulk', $order->id), [
                'stock_unit_ids' => json_encode([$soldUnit2->id]),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $replacement2->refresh();
        $this->assertTrue((bool) $replacement2->is_replaced);
        $this->assertSame($soldUnit2->id, (int) $replacement2->replaced_from_stock_id);

        // 3. Verify Notification Hub received order_stock_replaced notifications pointing to order detail
        $notifs = $customer->notifications()->get();
        $this->assertCount(2, $notifs);
        $this->assertSame('order_stock_replaced', $notifs->first()->data['type'] ?? null);
        $this->assertStringContainsString('/orders/' . $order->id, (string) ($notifs->first()->data['url'] ?? ''));

        // 4. Verify Telegram Bot message sent with MiniApp (web_app) button directly to /orders/{id}?token=...
        Http::assertSent(function ($req) use ($order, $customer) {
            if (!str_contains($req->url(), '/sendMessage')) {
                return false;
            }
            if ((string) ($req['chat_id'] ?? '') !== (string) $customer->telegram_id) {
                return false;
            }
            $markup = json_decode((string) ($req['reply_markup'] ?? '{}'), true);
            $btn = $markup['inline_keyboard'][0][0] ?? [];
            $webAppUrl = (string) ($btn['web_app']['url'] ?? '');

            return str_contains((string) ($req['text'] ?? ''), $order->order_ref)
                && str_contains($webAppUrl, "/orders/{$order->id}?token=");
        });

        // 5. Verify both Admin and Customer order detail pages display clear replacement markers!
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order->id))
            ->assertStatus(200)
            ->assertSee('Diganti oleh Admin')
            ->assertSee('fresh_user_1')
            ->assertSee('#' . $soldUnit1->id);

        $this->actingAs($customer)
            ->get(route('orders.show', $order->id))
            ->assertStatus(200)
            ->assertSee('Akun Pengganti Baru (Diganti Admin)')
            ->assertSee('fresh_user_1')
            ->assertSee('fresh_user_2');
    }
}
