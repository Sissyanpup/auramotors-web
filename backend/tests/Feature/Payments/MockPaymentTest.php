<?php

namespace Tests\Feature\Payments;

use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_mark_own_pending_mock_transaction_as_paid(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $vehicle = Vehicle::factory()->approved()->create();
        $transaction = Transaction::factory()->for($vehicle)->create(['buyer_id' => $buyer->id]);

        $response = $this->actingAs($buyer)->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay");

        $response->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'payment_status' => 'paid']);
    }

    public function test_buyer_can_mark_own_pending_mock_transaction_as_failed(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $vehicle = Vehicle::factory()->approved()->create();
        $transaction = Transaction::factory()->for($vehicle)->create(['buyer_id' => $buyer->id]);

        $response = $this->actingAs($buyer)->postJson("/api/payments/mock/{$transaction->gateway_reference}/fail");

        $response->assertOk()->assertJsonPath('data.payment_status', 'failed');
    }

    public function test_buyer_cannot_flip_another_buyers_transaction(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Buyer]);
        $intruder = User::factory()->create(['role' => UserRole::Buyer]);
        $vehicle = Vehicle::factory()->approved()->create();
        $transaction = Transaction::factory()->for($vehicle)->create(['buyer_id' => $owner->id]);

        $this->actingAs($intruder)
            ->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay")
            ->assertForbidden();
    }

    public function test_already_paid_transaction_cannot_be_paid_again(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $vehicle = Vehicle::factory()->approved()->create();
        $transaction = Transaction::factory()->paid()->for($vehicle)->create(['buyer_id' => $buyer->id]);

        $this->actingAs($buyer)
            ->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay")
            ->assertConflict();
    }
}
