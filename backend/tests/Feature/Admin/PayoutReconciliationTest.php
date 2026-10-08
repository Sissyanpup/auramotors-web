<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_commission_vs_payout_totals_from_paid_disbursements_only(): void
    {
        config(['payout.commission_rate' => 0.03]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $paidOne = Transaction::factory()->payoutRelease()->create(['amount' => 100_000_000]);
        $this->actingAs($admin)->postJson("/api/admin/transactions/{$paidOne->id}/disburse", ['reference' => 'TRF-1'])->assertOk();

        $paidTwo = Transaction::factory()->payoutRelease()->create(['amount' => 50_000_000]);
        $this->actingAs($admin)->postJson("/api/admin/transactions/{$paidTwo->id}/disburse", ['reference' => 'TRF-2'])->assertOk();

        // Not yet disbursed — must not count toward the reconciliation totals.
        Transaction::factory()->payoutRelease()->create(['amount' => 999_000_000]);

        $response = $this->actingAs($admin)->getJson('/api/admin/payouts/reconciliation');

        $response->assertOk()
            ->assertJsonPath('summary.paid_count', 2)
            ->assertJsonPath('summary.total_commission', '4500000.00')
            ->assertJsonPath('summary.total_payout', '145500000.00');
    }

    public function test_non_admin_cannot_view_reconciliation_report(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $this->actingAs($seller)->getJson('/api/admin/payouts/reconciliation')->assertForbidden();
    }
}
