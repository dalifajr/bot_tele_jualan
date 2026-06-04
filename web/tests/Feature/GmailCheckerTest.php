<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmailCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::create([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $this->product = Product::create([
            'name' => 'Gmail Accounts',
            'slug' => 'gmail-accounts',
            'description' => 'Test gmail product',
            'price' => 5000,
            'status' => 'active',
            'type' => 'account',
        ]);
    }

    /**
     * Guest user is redirected.
     */
    public function test_guest_is_redirected_from_gmail_checker(): void
    {
        $response = $this->get(route('admin.tools.gmail-checker'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Customer cannot access gmail checker.
     */
    public function test_customer_cannot_access_gmail_checker(): void
    {
        $response = $this->actingAs($this->customer)
            ->get(route('admin.tools.gmail-checker'));

        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    /**
     * Admin can access gmail checker.
     */
    public function test_admin_can_access_gmail_checker(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.gmail-checker'));

        $response->assertStatus(200);
        $response->assertSee('Gmail Live Checker');
    }

    /**
     * Admin can load stock and extract emails.
     */
    public function test_admin_can_load_stock_gmail(): void
    {
        // Create stock units
        StockUnit::create([
            'product_id' => $this->product->id,
            'raw_text' => 'Email: sjurokanda@gmail.com | Pass: 123',
            'is_sold' => false,
            'stock_status' => 'ready',
        ]);

        StockUnit::create([
            'product_id' => $this->product->id,
            'raw_text' => 'zafrangnwn@gmail.com:password123',
            'is_sold' => false,
            'stock_status' => 'ready',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tools.gmail-checker.load-stock'), [
                'product_id' => $this->product->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count' => 2,
        ]);
        $this->assertStringContainsString('sjurokanda@gmail.com', $response->content());
        $this->assertStringContainsString('zafrangnwn@gmail.com', $response->content());
    }

    /**
     * Admin can perform bulk action.
     */
    public function test_admin_can_perform_bulk_actions_gmail(): void
    {
        $stock1 = StockUnit::create([
            'product_id' => $this->product->id,
            'raw_text' => 'sjurokanda@gmail.com:123',
            'is_sold' => false,
            'stock_status' => 'ready',
        ]);

        $stock2 = StockUnit::create([
            'product_id' => $this->product->id,
            'raw_text' => 'zafrangnwn@gmail.com:123',
            'is_sold' => false,
            'stock_status' => 'ready',
        ]);

        // Test update status
        $response1 = $this->actingAs($this->admin)
            ->post(route('admin.tools.gmail-checker.bulk-action'), [
                'action' => 'update_status',
                'stock_ids' => [$stock1->id],
                'status' => 'saved_for_verification',
            ]);

        $response1->assertStatus(200);
        $response1->assertJson(['success' => true]);
        $this->assertEquals('saved_for_verification', $stock1->fresh()->stock_status);

        // Test delete
        $response2 = $this->actingAs($this->admin)
            ->post(route('admin.tools.gmail-checker.bulk-action'), [
                'action' => 'delete',
                'stock_ids' => [$stock2->id],
            ]);

        $response2->assertStatus(200);
        $response2->assertJson(['success' => true]);
        $this->assertNull(StockUnit::find($stock2->id));
    }
}
