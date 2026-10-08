<?php

namespace Tests\Feature;

use App\Enums\EscrowStatus;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscrowStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_mock_transaction_as_paid_holds_funds_in_escrow_and_marks_vehicle_sold(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = Vehicle::factory()->approved()->create(['seller_id' => $seller->id]);
        $transaction = Transaction::factory()->for($vehicle)->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);

        $this->actingAs($buyer)->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay")
            ->assertOk()
            ->assertJsonPath('data.escrow_status', 'escrow_hold');

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'escrow_status' => 'escrow_hold']);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'status' => VehicleStatus::Sold->value]);
        $this->assertDatabaseHas('transaction_status_histories', [
            'transaction_id' => $transaction->id,
            'from_status' => null,
            'to_status' => 'escrow_hold',
            'actor_id' => null,
        ]);
    }

    public function test_seller_can_view_and_list_own_sale_transactions(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $otherSeller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = Vehicle::factory()->approved()->create(['seller_id' => $seller->id]);
        $transaction = Transaction::factory()->escrowHold()->for($vehicle)->create(['seller_id' => $seller->id]);

        $this->actingAs($seller)->getJson('/api/seller/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $transaction->id);

        $this->actingAs($seller)->getJson("/api/seller/transactions/{$transaction->id}")->assertOk();
        $this->actingAs($otherSeller)->getJson("/api/seller/transactions/{$transaction->id}")->assertForbidden();
    }

    public function test_buyer_and_seller_confirm_handover_then_admin_approves_transition_to_serah_terima(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = Vehicle::factory()->approved()->create(['seller_id' => $seller->id]);
        $transaction = Transaction::factory()->escrowHold()->for($vehicle)->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/approve-handover")
            ->assertUnprocessable();

        $this->actingAs($buyer)->postJson("/api/buyer/transactions/{$transaction->id}/confirm-handover")
            ->assertOk()
            ->assertJsonPath('data.escrow_status', 'escrow_hold');

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/approve-handover")
            ->assertUnprocessable();

        $this->actingAs($seller)->postJson("/api/seller/transactions/{$transaction->id}/confirm-handover")
            ->assertOk();

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/approve-handover", ['note' => 'Sudah dicek dokumen fisik.'])
            ->assertOk()
            ->assertJsonPath('data.escrow_status', 'serah_terima');

        $this->assertDatabaseHas('transaction_status_histories', [
            'transaction_id' => $transaction->id,
            'from_status' => 'escrow_hold',
            'to_status' => 'serah_terima',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_full_happy_path_from_escrow_hold_to_selesai(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->serahTerima()->create();

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/approve-payout")
            ->assertOk()
            ->assertJsonPath('data.escrow_status', 'payout_release');

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/mark-completed")
            ->assertConflict();

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse", ['reference' => 'TRF-001'])
            ->assertOk()
            ->assertJsonPath('data.payout_status', 'paid');

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/mark-completed")
            ->assertOk()
            ->assertJsonPath('data.escrow_status', 'selesai');

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/approve-payout")
            ->assertConflict();
    }

    public function test_buyer_can_open_dispute_while_funds_are_held(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $transaction = Transaction::factory()->escrowHold()->create(['buyer_id' => $buyer->id]);

        $this->actingAs($buyer)->postJson("/api/buyer/transactions/{$transaction->id}/dispute", [
            'reason' => 'Kondisi kendaraan tidak sesuai deskripsi.',
        ])->assertOk()->assertJsonPath('data.escrow_status', 'dispute');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'escrow_status' => 'dispute',
            'escrow_status_before_dispute' => 'escrow_hold',
            'disputed_by' => $buyer->id,
        ]);
    }

    public function test_unrelated_user_cannot_open_dispute(): void
    {
        $intruder = User::factory()->create(['role' => UserRole::Buyer]);
        $transaction = Transaction::factory()->escrowHold()->create();

        $this->actingAs($intruder)->postJson("/api/buyer/transactions/{$transaction->id}/dispute", [
            'reason' => 'Coba-coba.',
        ])->assertForbidden();
    }

    public function test_admin_resolves_dispute_with_refund_and_reopens_vehicle_listing(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = Vehicle::factory()->create(['seller_id' => $seller->id, 'status' => VehicleStatus::Sold]);
        $transaction = Transaction::factory()->escrowHold()->for($vehicle)->create(['seller_id' => $seller->id]);
        $transaction->update([
            'escrow_status' => EscrowStatus::Dispute,
            'escrow_status_before_dispute' => 'escrow_hold',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/resolve-dispute", [
            'resolution' => 'refund',
            'note' => 'Kendaraan tidak sesuai, dana dikembalikan ke buyer.',
        ])->assertOk()->assertJsonPath('data.escrow_status', 'refunded');

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'status' => VehicleStatus::Approved->value]);
    }

    public function test_admin_resolves_dispute_by_resuming_previous_state(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->serahTerima()->create();
        $transaction->update([
            'escrow_status' => EscrowStatus::Dispute,
            'escrow_status_before_dispute' => 'serah_terima',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/resolve-dispute", [
            'resolution' => 'resume',
        ])->assertOk()->assertJsonPath('data.escrow_status', 'serah_terima');
    }

    public function test_non_admin_cannot_access_admin_escrow_endpoints(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $transaction = Transaction::factory()->escrowHold()->create();

        $this->actingAs($seller)->getJson('/api/admin/transactions')->assertForbidden();
        $this->actingAs($seller)->postJson("/api/admin/transactions/{$transaction->id}/approve-handover")->assertForbidden();
    }
}
