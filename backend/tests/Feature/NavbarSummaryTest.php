<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_notification_items_match_notification_count(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = Vehicle::factory()->approved()->create(['seller_id' => $seller->id]);
        $transaction = Transaction::factory()->escrowHold()->for($vehicle)->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);

        $this->actingAs($seller)->getJson('/api/navbar-summary')
            ->assertOk()
            ->assertJsonPath('data.notifications', 1)
            ->assertJsonCount(1, 'data.notification_items')
            ->assertJsonPath('data.notification_items.0.href', "/seller/transactions/{$transaction->id}");
    }

    public function test_buyer_notification_items_match_notification_count(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = Vehicle::factory()->approved()->create(['seller_id' => $seller->id]);
        $transaction = Transaction::factory()->serahTerima()->for($vehicle)->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'buyer_confirmed_at' => null]);

        $this->actingAs($buyer)->getJson('/api/navbar-summary')
            ->assertOk()
            ->assertJsonPath('data.notifications', 1)
            ->assertJsonCount(1, 'data.notification_items')
            ->assertJsonPath('data.notification_items.0.href', "/buyer/transactions/{$transaction->id}");
    }

    public function test_admin_notification_items_include_pending_vehicle_reviews(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        Vehicle::factory()->create(['seller_id' => $seller->id, 'status' => VehicleStatus::PendingReview]);

        $this->actingAs($admin)->getJson('/api/navbar-summary')
            ->assertOk()
            ->assertJsonPath('data.notifications', 1)
            ->assertJsonCount(1, 'data.notification_items')
            ->assertJsonPath('data.notification_items.0.href', '/admin/vehicles');
    }
}
