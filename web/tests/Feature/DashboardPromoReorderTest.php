<?php

namespace Tests\Feature;

use App\Models\DashboardPromo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPromoReorderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DashboardPromo::query()->delete();

        $this->admin = User::forceCreate([
            'username' => 'admin_promo_order',
            'full_name' => 'Admin Promo',
            'email' => 'admin_promo_order@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);
    }

    protected function createPromo(string $title, int $orderIndex = 0, bool $active = true): DashboardPromo
    {
        return DashboardPromo::create([
            'title' => $title,
            'badge_text' => 'PROMO',
            'badge_color' => '#0b57d0',
            'description' => 'Deskripsi ' . $title,
            'icon' => 'fas fa-tags',
            'button_text' => 'Lihat',
            'button_url' => '/catalog',
            'gradient_theme' => 'blue',
            'order_index' => $orderIndex,
            'is_active' => $active,
        ]);
    }

    public function test_move_up_and_down_normalizes_legacy_duplicate_order_indexes_and_swaps_deterministically(): void
    {
        // Legacy messy data: duplicate 0s and gap 9
        $p1 = $this->createPromo('Promo Pertama', 0);
        $p2 = $this->createPromo('Promo Kedua', 0);
        $p3 = $this->createPromo('Promo Ketiga', 9);

        // Move Promo Ketiga UP -> becomes #2
        $resUp = $this->actingAs($this->admin)
            ->postJson(route('admin.promos.move', $p3), ['direction' => 'up']);

        $resUp->assertStatus(200);
        $resUp->assertJsonPath('status', 'success');
        $resUp->assertJsonPath('ordered_ids', [$p1->id, $p3->id, $p2->id]);

        $this->assertEquals([1, 2, 3], [
            $p1->fresh()->order_index,
            $p3->fresh()->order_index,
            $p2->fresh()->order_index,
        ]);

        // Move Promo Pertama DOWN -> swaps with Promo Ketiga (#1 <-> #2)
        $resDown = $this->actingAs($this->admin)
            ->postJson(route('admin.promos.move', $p1), ['direction' => 'down']);

        $resDown->assertStatus(200);
        $resDown->assertJsonPath('ordered_ids', [$p3->id, $p1->id, $p2->id]);

        // Boundary check: moving top item UP stays safe at #1
        $resBoundary = $this->actingAs($this->admin)
            ->postJson(route('admin.promos.move', $p3), ['direction' => 'up']);
        $resBoundary->assertStatus(200);
        $resBoundary->assertJsonPath('ordered_ids', [$p3->id, $p1->id, $p2->id]);
    }

    public function test_batch_drag_and_drop_reorder_updates_sequence_and_dashboard_matches(): void
    {
        $p1 = $this->createPromo('Promo Alpha', 1);
        $p2 = $this->createPromo('Promo Beta', 2);
        $p3 = $this->createPromo('Promo Gamma', 3);

        $res = $this->actingAs($this->admin)
            ->postJson(route('admin.promos.reorder'), [
                'ordered_ids' => [$p3->id, $p1->id, $p2->id],
            ]);

        $res->assertStatus(200);
        $res->assertJsonPath('ordered_ids', [$p3->id, $p1->id, $p2->id]);

        $orderedTitles = DashboardPromo::active()->ordered()->pluck('title')->all();
        $this->assertEquals(['Promo Gamma', 'Promo Alpha', 'Promo Beta'], $orderedTitles);

        // Index page renders interactive up/down buttons and #1..#3 badges
        $indexRes = $this->actingAs($this->admin)->get(route('admin.promos.index'));
        $indexRes->assertStatus(200);
        $indexRes->assertSee('btn-move-up');
        $indexRes->assertSee('btn-move-down');
        $indexRes->assertSee('#1');
        $indexRes->assertSee('#3');
    }

    public function test_store_supports_top_and_bottom_placement_and_destroy_normalizes_remaining(): void
    {
        $p1 = $this->createPromo('Existing Promo', 1);

        // Store with placement = top -> becomes #1
        $this->actingAs($this->admin)->post(route('admin.promos.store'), [
            'title' => 'Top Promo',
            'badge_text' => 'NEW',
            'badge_color' => '#0b57d0',
            'description' => 'Di paling atas',
            'icon' => 'fas fa-star',
            'button_text' => 'Buka',
            'button_url' => '/catalog',
            'gradient_theme' => 'blue',
            'placement' => 'top',
            'is_active' => '1',
        ])->assertRedirect(route('admin.promos.index'));

        // Store with placement = bottom -> becomes #3
        $this->actingAs($this->admin)->post(route('admin.promos.store'), [
            'title' => 'Bottom Promo',
            'badge_text' => 'LAST',
            'badge_color' => '#0b57d0',
            'description' => 'Di paling bawah',
            'icon' => 'fas fa-tag',
            'button_text' => 'Buka',
            'button_url' => '/catalog',
            'gradient_theme' => 'green',
            'placement' => 'bottom',
            'is_active' => '1',
        ])->assertRedirect(route('admin.promos.index'));

        $ordered = DashboardPromo::ordered()->pluck('title')->all();
        $this->assertEquals(['Top Promo', 'Existing Promo', 'Bottom Promo'], $ordered);

        // Delete middle item -> remaining normalize to 1, 2
        $this->actingAs($this->admin)->delete(route('admin.promos.destroy', $p1))
            ->assertRedirect(route('admin.promos.index'));

        $remaining = DashboardPromo::ordered()->get();
        $this->assertEquals([1, 2], $remaining->pluck('order_index')->all());
        $this->assertEquals(['Top Promo', 'Bottom Promo'], $remaining->pluck('title')->all());
    }
}
