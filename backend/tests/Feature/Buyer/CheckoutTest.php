<?php

namespace Tests\Feature\Buyer;

use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_checkout_approved_vehicle_and_receives_mock_invoice(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $response = $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'amount' => 20_000_000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.payment_gateway', 'mock');
        $this->assertDatabaseHas('transactions', [
            'vehicle_id' => $vehicle->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $vehicle->seller_id,
            'payment_status' => 'pending',
        ]);
        $transaction = Transaction::firstOrFail();
        $this->assertNotNull($transaction->gateway_reference);
        $this->assertStringContainsString($transaction->gateway_reference, $transaction->gateway_invoice_url);
    }

    public function test_dp_amount_below_ten_percent_is_rejected(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'amount' => 1_000_000,
        ])->assertUnprocessable();
    }

    public function test_seller_cannot_checkout_own_vehicle(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = Vehicle::factory()->approved()->create(['seller_id' => $seller->id]);

        $this->actingAs($seller)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'amount' => $vehicle->price * 0.2,
        ])->assertForbidden();
    }

    public function test_buyer_cannot_checkout_vehicle_that_is_not_approved(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::PendingReview, 'price' => 100_000_000]);

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'amount' => 20_000_000,
        ])->assertForbidden();
    }

    public function test_buyer_cannot_checkout_vehicle_with_an_active_paid_transaction(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);
        Transaction::factory()->paid()->for($vehicle)->create();

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'amount' => 20_000_000,
        ])->assertConflict();
    }

    public function test_buyer_can_view_own_transaction_but_not_others(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $otherBuyer = User::factory()->create(['role' => UserRole::Buyer]);
        $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id]);

        $this->actingAs($buyer)->getJson("/api/buyer/transactions/{$transaction->id}")->assertOk();
        $this->actingAs($otherBuyer)->getJson("/api/buyer/transactions/{$transaction->id}")->assertForbidden();
    }
}
