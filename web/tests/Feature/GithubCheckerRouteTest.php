<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\GithubCheckBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use Illuminate\Support\Facades\Schema;

class GithubCheckerRouteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

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
    }

    /**
     * Guest user should be redirected to login.
     */
    public function test_guest_is_redirected_from_github_checker(): void
    {
        $response = $this->get(route('admin.tools.github-checker'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Non-admin user should be aborted or redirected.
     */
    public function test_customer_cannot_access_github_checker(): void
    {
        $response = $this->actingAs($this->customer)
            ->get(route('admin.tools.github-checker'));

        // Depending on admin middleware implementation, it might redirect or abort (403/302)
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    /**
     * Admin user can access github checker.
     */
    public function test_admin_can_access_github_checker(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.github-checker'));

        $response->assertStatus(200);
        $response->assertSee('GitHub Live Checker');
    }
}
