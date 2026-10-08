<?php

namespace Tests\Feature\Admin;

use App\Enums\SellerProfileStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_disburses_manual_payout_and_computes_commission_split(): void
    {
        config(['payout.commission_rate' => 0.03]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->payoutRelease()->create(['amount' => 100_000_000]);

        $response = $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse", [
            'reference' => 'TRF-20260917-001',
            'note' => 'Transfer manual via BCA.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.payout_status', 'paid')
            ->assertJsonPath('data.payouts.0.status', 'paid')
            ->assertJsonPath('data.payouts.0.commission_amount', '3000000.00')
            ->assertJsonPath('data.payouts.0.payout_amount', '97000000.00')
            ->assertJsonPath('data.payouts.0.reference', 'TRF-20260917-001');

        $this->assertDatabaseHas('transaction_payouts', [
            'transaction_id' => $transaction->id,
            'method' => 'manual',
            'status' => 'paid',
            'commission_amount' => 3_000_000,
            'payout_amount' => 97_000_000,
        ]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'payout_status' => 'paid']);
    }

    public function test_manual_payout_requires_a_reference(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->payoutRelease()->create();

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference');
    }

    public function test_cannot_disburse_before_payout_release_is_approved(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->serahTerima()->create();

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse", ['reference' => 'TRF-1'])
            ->assertConflict();
    }

    public function test_cannot_disburse_the_same_transaction_twice(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->payoutRelease()->create();

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse", ['reference' => 'TRF-1'])
            ->assertOk();

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse", ['reference' => 'TRF-2'])
            ->assertConflict();
    }

    public function test_mark_completed_requires_payout_to_be_paid_first(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->payoutRelease()->create();

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/mark-completed")
            ->assertConflict();
    }

    public function test_non_admin_cannot_trigger_disbursement(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $transaction = Transaction::factory()->payoutRelease()->create();

        $this->actingAs($seller)->postJson("/api/admin/transactions/{$transaction->id}/disburse", ['reference' => 'TRF-1'])
            ->assertForbidden();
    }

    public function test_xendit_driver_disburses_to_sellers_bank_account(): void
    {
        config(['payout.driver' => 'xendit', 'payout.xendit.secret_key' => 'xnd_development_test']);
        Http::fake([
            'api.xendit.co/disbursements' => Http::response([
                'id' => 'xnd-disb-1',
                'status' => 'COMPLETED',
            ]),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => SellerProfileStatus::Approved,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder_name' => $seller->name,
        ]);
        $transaction = Transaction::factory()->payoutRelease()->create(['seller_id' => $seller->id, 'amount' => 50_000_000]);

        $response = $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse");

        $response->assertOk()
            ->assertJsonPath('data.payout_status', 'paid')
            ->assertJsonPath('data.payouts.0.reference', 'xnd-disb-1');
    }

    public function test_xendit_driver_fails_when_seller_has_no_bank_account_on_file(): void
    {
        config(['payout.driver' => 'xendit', 'payout.xendit.secret_key' => 'xnd_development_test']);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => SellerProfileStatus::Approved,
        ]);
        $transaction = Transaction::factory()->payoutRelease()->create(['seller_id' => $seller->id]);

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse")
            ->assertUnprocessable();
    }
}
