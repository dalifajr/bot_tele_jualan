<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoadmapFeaturesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that order rate limiting prevents customer from having more than 2 pending payment orders.
     */
    public function test_checkout_rate_limiting()
    {
        $customer = User::create([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Premium Account',
            'price' => 50000,
        ]);

        // Add enough stock units for checkout
        for ($i = 0; $i < 5; $i++) {
            StockUnit::create([
                'product_id' => $product->id,
                'raw_text' => "Credentials {$i}",
                'is_sold' => false,
                'stock_status' => 'ready',
            ]);
        }

        $this->actingAs($customer);

        // 1st checkout should succeed
        $response1 = $this->post(route('checkout.store', $product->id), ['quantity' => 1]);
        $response1->assertRedirect();
        $this->assertEquals(1, Order::where('customer_id', $customer->id)->where('status', 'pending_payment')->count());

        // 2nd checkout should succeed
        $response2 = $this->post(route('checkout.store', $product->id), ['quantity' => 1]);
        $response2->assertRedirect();
        $this->assertEquals(2, Order::where('customer_id', $customer->id)->where('status', 'pending_payment')->count());

        // 3rd checkout should fail and redirect back with error
        $response3 = $this->post(route('checkout.store', $product->id), ['quantity' => 1]);
        $response3->assertStatus(302);
        $response3->assertSessionHas('error', 'Anda memiliki terlalu banyak pesanan yang menunggu pembayaran. Silakan selesaikan atau batalkan pesanan Anda sebelumnya.');
        
        // Assert order count is still 2
        $this->assertEquals(2, Order::where('customer_id', $customer->id)->where('status', 'pending_payment')->count());
    }

    /**
     * Test that artisan orders:release-expired command cancels expired pending orders and releases stock.
     */
    public function test_cron_auto_release_stock()
    {
        $customer = User::create([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Premium Account',
            'price' => 50000,
        ]);

        // Create expired order
        $expiredOrder = Order::create([
            'order_ref' => 'ORD-EXPIRED',
            'customer_id' => $customer->id,
            'subtotal' => 50000,
            'unique_code' => 12,
            'total_amount' => 50012,
            'status' => 'pending_payment',
            'expires_at' => now()->subMinutes(5),
        ]);

        OrderItem::create([
            'order_id' => $expiredOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        $expiredStock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Expired Stock Credentials',
            'is_sold' => false,
            'stock_status' => 'reserved_checkout',
            'sold_order_id' => $expiredOrder->id,
        ]);

        // Create a non-expired (active) order
        $activeOrder = Order::create([
            'order_ref' => 'ORD-ACTIVE',
            'customer_id' => $customer->id,
            'subtotal' => 50000,
            'unique_code' => 54,
            'total_amount' => 50054,
            'status' => 'pending_payment',
            'expires_at' => now()->addMinutes(15),
        ]);

        OrderItem::create([
            'order_id' => $activeOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        $activeStock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Active Stock Credentials',
            'is_sold' => false,
            'stock_status' => 'reserved_checkout',
            'sold_order_id' => $activeOrder->id,
        ]);

        // Run the auto-release command
        Artisan::call('orders:release-expired');

        // Reload data from DB
        $expiredOrder->refresh();
        $activeOrder->refresh();
        $expiredStock->refresh();
        $activeStock->refresh();

        // Expired order assertions
        $this->assertEquals('expired', $expiredOrder->status);
        $this->assertNotNull($expiredOrder->cancelled_at);
        $this->assertEquals('Batas waktu pembayaran telah habis (Sistem)', $expiredOrder->cancel_reason);
        $this->assertEquals('ready', $expiredStock->stock_status);
        $this->assertNull($expiredStock->sold_order_id);

        // Active order assertions
        $this->assertEquals('pending_payment', $activeOrder->status);
        $this->assertNull($activeOrder->cancelled_at);
        $this->assertEquals('reserved_checkout', $activeStock->stock_status);
        $this->assertEquals($activeOrder->id, $activeStock->sold_order_id);
    }

    /**
     * Test admin can replace problematic stock unit with another ready stock from the same seller.
     */
    public function test_replace_stock_by_admin()
    {
        $admin = User::create([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $seller = User::create([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'seller_save_hours' => 12,
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Product Test',
            'price' => 50000,
            'creator_id' => $seller->id,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-123',
            'customer_id' => $seller->id,
            'subtotal' => 50000,
            'unique_code' => 0,
            'total_amount' => 50000,
            'status' => 'delivered',
        ]);

        $problemStock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Problem stock credentials',
            'is_sold' => true,
            'stock_status' => 'ready',
            'sold_order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        $newStock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Good replacement stock',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller->id,
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.orders.replace-stock', [$order->id, $problemStock->id]));

        $response->assertRedirect();
        
        $problemStock->refresh();
        $newStock->refresh();

        $this->assertFalse($problemStock->is_sold);
        $this->assertNull($problemStock->sold_order_id);
        $this->assertEquals('saved_for_verification', $problemStock->stock_status);
        $this->assertNotNull($problemStock->available_at);

        $this->assertTrue($newStock->is_sold);
        $this->assertEquals($order->id, $newStock->sold_order_id);
    }

    /**
     * Test admin refunding order cancels held funds and moves stock back to saved_for_verification.
     */
    public function test_refund_order_by_admin()
    {
        $admin = User::create([
            'username' => 'admin_test2',
            'full_name' => 'Admin Test 2',
            'email' => 'admin2@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $seller = User::create([
            'username' => 'seller_test2',
            'full_name' => 'Seller Test 2',
            'email' => 'seller2@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Product Test',
            'price' => 50000,
            'warranty_days' => 3,
            'creator_id' => $seller->id,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-456',
            'customer_id' => $seller->id,
            'subtotal' => 50000,
            'unique_code' => 0,
            'total_amount' => 50000,
            'status' => 'delivered',
        ]);

        $stock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Some credentials',
            'is_sold' => true,
            'stock_status' => 'ready',
            'sold_order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        // Create held funds record
        DB::table('held_funds')->insert([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount' => 45000,
            'status' => 'held',
            'release_at' => now()->addDays(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.orders.refund', $order->id));

        $response->assertRedirect();
        
        $order->refresh();
        $stock->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertFalse($stock->is_sold);
        $this->assertEquals('saved_for_verification', $stock->stock_status);

        $heldFund = DB::table('held_funds')->where('order_id', $order->id)->first();
        $this->assertEquals('cancelled', $heldFund->status);
    }

    /**
     * Test command releases held funds and notifies seller.
     */
    public function test_release_held_funds_command()
    {
        $seller = User::create([
            'username' => 'seller_test3',
            'full_name' => 'Seller Test 3',
            'email' => 'seller3@test.com',
            'role' => 'seller',
            'wallet_balance' => 1000,
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Product Test',
            'price' => 50000,
            'warranty_days' => 3,
            'creator_id' => $seller->id,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-789',
            'customer_id' => $seller->id,
            'subtotal' => 50000,
            'unique_code' => 0,
            'total_amount' => 50000,
            'status' => 'delivered',
        ]);

        // Held fund that is ready for release
        DB::table('held_funds')->insert([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount' => 45000,
            'status' => 'held',
            'release_at' => now()->subMinutes(5),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        // Held fund that is not ready for release
        DB::table('held_funds')->insert([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount' => 30000,
            'status' => 'held',
            'release_at' => now()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('funds:release-held');

        $seller->refresh();
        $this->assertEquals(46000, $seller->wallet_balance); // 1000 + 45000

        $fund1 = DB::table('held_funds')->where('amount', 45000)->first();
        $this->assertEquals('released', $fund1->status);

        $fund2 = DB::table('held_funds')->where('amount', 30000)->first();
        $this->assertEquals('held', $fund2->status);
    }

    /**
     * Test admin can replace stock units in bulk.
     */
    public function test_replace_stock_bulk_by_admin()
    {
        $admin = User::create([
            'username' => 'admin_bulk_rep',
            'full_name' => 'Admin Bulk Replace',
            'email' => 'admin_bulk_rep@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $seller = User::create([
            'username' => 'seller_bulk_rep',
            'full_name' => 'Seller Bulk Replace',
            'email' => 'seller_bulk_rep@test.com',
            'role' => 'seller',
            'seller_save_hours' => 12,
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Product Bulk Replace',
            'price' => 50000,
            'creator_id' => $seller->id,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-REP-BULK',
            'customer_id' => $seller->id,
            'subtotal' => 100000,
            'unique_code' => 0,
            'total_amount' => 100000,
            'status' => 'delivered',
        ]);

        $problem1 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Problem 1',
            'is_sold' => true,
            'stock_status' => 'ready',
            'sold_order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        $problem2 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Problem 2',
            'is_sold' => true,
            'stock_status' => 'ready',
            'sold_order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        $rep1 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Replacement 1',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller->id,
        ]);

        $rep2 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Replacement 2',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller->id,
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.orders.replace-stock-bulk', $order->id), [
            'stock_unit_ids' => json_encode([$problem1->id, $problem2->id])
        ]);

        $response->assertRedirect();

        $problem1->refresh();
        $problem2->refresh();
        $rep1->refresh();
        $rep2->refresh();

        $this->assertFalse($problem1->is_sold);
        $this->assertEquals('saved_for_verification', $problem1->stock_status);
        $this->assertFalse($problem2->is_sold);
        $this->assertEquals('saved_for_verification', $problem2->stock_status);

        $this->assertTrue($rep1->is_sold);
        $this->assertEquals($order->id, $rep1->sold_order_id);
        $this->assertTrue($rep2->is_sold);
        $this->assertEquals($order->id, $rep2->sold_order_id);
    }

    /**
     * Test admin can refund stock units in bulk.
     */
    public function test_refund_stock_bulk_by_admin()
    {
        $admin = User::create([
            'username' => 'admin_bulk_ref',
            'full_name' => 'Admin Bulk Refund',
            'email' => 'admin_bulk_ref@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $seller = User::create([
            'username' => 'seller_bulk_ref',
            'full_name' => 'Seller Bulk Refund',
            'email' => 'seller_bulk_ref@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Product Bulk Refund',
            'price' => 50000,
            'creator_id' => $seller->id,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-REF-BULK2',
            'customer_id' => $seller->id,
            'subtotal' => 150000,
            'unique_code' => 0,
            'total_amount' => 150000,
            'status' => 'delivered',
        ]);

        $unit1 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Unit 1',
            'is_sold' => true,
            'stock_status' => 'ready',
            'sold_order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        $unit2 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Unit 2',
            'is_sold' => true,
            'stock_status' => 'ready',
            'sold_order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        $unit3 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Unit 3',
            'is_sold' => true,
            'stock_status' => 'ready',
            'sold_order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        // 3 held funds records
        for ($i = 0; $i < 3; $i++) {
            DB::table('held_funds')->insert([
                'seller_id' => $seller->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'amount' => 45000,
                'status' => 'held',
                'release_at' => now()->addDays(3),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin);

        // Refund 2 of 3 units (Partial Refund)
        $response = $this->post(route('admin.orders.refund-bulk', $order->id), [
            'stock_unit_ids' => json_encode([$unit1->id, $unit2->id])
        ]);

        $response->assertRedirect();

        $order->refresh();
        $unit1->refresh();
        $unit2->refresh();
        $unit3->refresh();

        // Order should still be delivered since 1 unit remains
        $this->assertEquals('delivered', $order->status);
        $this->assertFalse($unit1->is_sold);
        $this->assertEquals('saved_for_verification', $unit1->stock_status);
        $this->assertFalse($unit2->is_sold);
        $this->assertEquals('saved_for_verification', $unit2->stock_status);
        $this->assertTrue($unit3->is_sold);

        // 2 of the held funds should be cancelled, 1 should remain held
        $cancelledFunds = DB::table('held_funds')->where('order_id', $order->id)->where('status', 'cancelled')->count();
        $heldFunds = DB::table('held_funds')->where('order_id', $order->id)->where('status', 'held')->count();

        $this->assertEquals(2, $cancelledFunds);
        $this->assertEquals(1, $heldFunds);

        // Now refund the last unit (which should trigger full cancellation of order)
        $response2 = $this->post(route('admin.orders.refund-bulk', $order->id), [
            'stock_unit_ids' => json_encode([$unit3->id])
        ]);

        $response2->assertRedirect();

        $order->refresh();
        $unit3->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertFalse($unit3->is_sold);
        $this->assertEquals('saved_for_verification', $unit3->stock_status);

        $totalCancelled = DB::table('held_funds')->where('order_id', $order->id)->where('status', 'cancelled')->count();
        $this->assertEquals(3, $totalCancelled);
    }

    /**
     * Test admin can impersonate another user.
     */
    public function test_admin_can_impersonate_user()
    {
        $admin = User::create([
            'username' => 'admin_imp',
            'full_name' => 'Admin Impersonator',
            'email' => 'admin_imp@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $customer = User::create([
            'username' => 'customer_imp',
            'full_name' => 'Customer Impersonated',
            'email' => 'customer_imp@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.users.impersonate', $customer->id));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('admin_impersonator_id', $admin->id);

        // Assert currently authenticated user is now the customer
        $this->assertEquals($customer->id, \Illuminate\Support\Facades\Auth::id());

        // Assert audit log was recorded
        $log = DB::table('audit_logs')
            ->where('action', 'admin_impersonate_start')
            ->where('entity_id', $customer->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString("impersonating user_id={$customer->id}", $log->detail);
    }

    /**
     * Test impersonated user can stop impersonating and return to admin session.
     */
    public function test_impersonated_user_can_stop_impersonating()
    {
        $admin = User::create([
            'username' => 'admin_imp2',
            'full_name' => 'Admin Impersonator 2',
            'email' => 'admin_imp2@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $customer = User::create([
            'username' => 'customer_imp2',
            'full_name' => 'Customer Impersonated 2',
            'email' => 'customer_imp2@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        // Start session as impersonated customer
        $this->actingAs($customer);
        session(['admin_impersonator_id' => $admin->id]);

        $response = $this->post(route('admin.users.stop-impersonating'));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertFalse(session()->has('admin_impersonator_id'));

        // Assert currently authenticated user is back to the admin
        $this->assertEquals($admin->id, \Illuminate\Support\Facades\Auth::id());

        // Assert audit log was recorded
        $log = DB::table('audit_logs')
            ->where('action', 'admin_impersonate_stop')
            ->where('actor_id', $admin->id)
            ->first();
        $this->assertNotNull($log);
    }

    /**
     * Test admin can trigger maintenance commands via settings web interface.
     */
    public function test_admin_can_run_maintenance_commands_via_web()
    {
        $admin = User::create([
            'username' => 'admin_maint',
            'full_name' => 'Admin Maintenance',
            'email' => 'admin_maint@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin);

        // 1. Run funds release
        $response1 = $this->post(route('admin.settings.run-held-funds'));
        $response1->assertRedirect();
        $response1->assertSessionHas('success');
        $this->assertStringContainsString('pelepasan saldo tertahan', session('success'));

        // 2. Run release expired
        $response2 = $this->post(route('admin.settings.run-release-expired'));
        $response2->assertRedirect();
        $response2->assertSessionHas('success');
        $this->assertStringContainsString('membatalkan pesanan kedaluwarsa', session('success'));
    }

    /**
     * Test admin orders filtering and search.
     */
    public function test_admin_orders_filtering_and_search()
    {
        $admin = User::create([
            'username' => 'admin_orders_test',
            'full_name' => 'Admin Orders Test',
            'email' => 'admin_orders@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $productNetflix = Product::create(['name' => 'Netflix Premium', 'price' => 5000]);
        $productYoutube = Product::create(['name' => 'Youtube Premium', 'price' => 10000]);

        $customerAlice = User::create([
            'username' => 'alice_buyer',
            'full_name' => 'Alice Smith',
            'email' => 'alice@test.com',
            'telegram_id' => '111111',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $customerBob = User::create([
            'username' => 'bob_buyer',
            'full_name' => 'Bob Jones',
            'email' => 'bob@test.com',
            'telegram_id' => '222222',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        // Order 1: Alice, Netflix, pending_payment
        $order1 = Order::create([
            'order_ref' => 'ORD-NETFLIX-ALICE',
            'customer_id' => $customerAlice->id,
            'subtotal' => 5000,
            'unique_code' => 1,
            'total_amount' => 5001,
            'status' => 'pending_payment',
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $productNetflix->id,
            'quantity' => 1,
            'unit_price' => 5000,
        ]);

        // Order 2: Bob, Youtube, delivered
        $order2 = Order::create([
            'order_ref' => 'ORD-YOUTUBE-BOB',
            'customer_id' => $customerBob->id,
            'subtotal' => 10000,
            'unique_code' => 2,
            'total_amount' => 10002,
            'status' => 'delivered',
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $productYoutube->id,
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        $this->actingAs($admin);

        // 1. Search by reference
        $response = $this->get(route('admin.orders.index', ['search' => 'ORD-NETFLIX']));
        $response->assertStatus(200);
        $response->assertSee('ORD-NETFLIX-ALICE');
        $response->assertDontSee('ORD-YOUTUBE-BOB');

        // 2. Search by customer username
        $response = $this->get(route('admin.orders.index', ['search' => 'alice_buyer']));
        $response->assertSee('ORD-NETFLIX-ALICE');
        $response->assertDontSee('ORD-YOUTUBE-BOB');

        // 3. Search by customer full name
        $response = $this->get(route('admin.orders.index', ['search' => 'Bob Jones']));
        $response->assertDontSee('ORD-NETFLIX-ALICE');
        $response->assertSee('ORD-YOUTUBE-BOB');

        // 4. Search by customer Telegram ID
        $response = $this->get(route('admin.orders.index', ['search' => '222222']));
        $response->assertDontSee('ORD-NETFLIX-ALICE');
        $response->assertSee('ORD-YOUTUBE-BOB');

        // 5. Search by product name
        $response = $this->get(route('admin.orders.index', ['search' => 'Netflix']));
        $response->assertSee('ORD-NETFLIX-ALICE');
        $response->assertDontSee('ORD-YOUTUBE-BOB');

        // 6. Filter by status
        $response = $this->get(route('admin.orders.index', ['status' => 'pending_payment']));
        $response->assertSee('ORD-NETFLIX-ALICE');
        $response->assertDontSee('ORD-YOUTUBE-BOB');

        // 7. Filter by product_id
        $response = $this->get(route('admin.orders.index', ['product_id' => $productYoutube->id]));
        $response->assertDontSee('ORD-NETFLIX-ALICE');
        $response->assertSee('ORD-YOUTUBE-BOB');
    }
}


