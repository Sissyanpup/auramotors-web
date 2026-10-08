<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_only_paid_transactions_and_can_filter_by_escrow_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Transaction::factory()->create(); // pending, should be excluded
        $held = Transaction::factory()->escrowHold()->create();
        Transaction::factory()->serahTerima()->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/transactions?escrow_status=escrow_hold');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($held->id));
        $this->assertCount(1, $ids);
    }

    public function test_admin_can_see_full_status_history_on_detail_view(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id]);

        $this->actingAs($buyer)->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay")->assertOk();

        $response = $this->actingAs($admin)->getJson("/api/admin/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('data.status_history.0.to_status', 'escrow_hold')
            ->assertJsonPath('data.status_history.0.actor', 'Sistem');
    }
}
