<?php

namespace Tests\Feature;

use App\Enums\PayoutMethod;
use App\Enums\SellerProfileStatus;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\ActivityLog;
use App\Models\Promo;
use App\Models\SellerBonus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sprint 12: log aktivitas global, bonus penjual, promo pembeli, laporan.
 */
class ActivityBonusPromoTest extends TestCase
{
    use RefreshDatabase;

    private const CARD_PAYMENT = [
        'method' => 'card',
        'card_number' => '4111 2222 3333 4444',
        'card_holder' => 'Budi Pembeli',
        'card_expiry' => '09/31',
        'card_cvv' => '123',
        'otp' => '482913',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'payout.commission_rate' => 0.03,
            'payout.driver' => PayoutMethod::Manual->value,
            'pricing.credit.auto_approve_seconds' => 60,
        ]);
    }

    public function test_verification_steps_are_logged_with_actor_and_kept_in_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Sari Penjual']);
        $profile = $seller->sellerProfile()->create(['ktp_path' => 'kyc/1/ktp.jpg', 'status' => SellerProfileStatus::Pending]);
        $vehicle = Vehicle::factory()->create(['seller_id' => $seller->id, 'status' => VehicleStatus::PendingReview]);

        $this->actingAs($admin)->patchJson("/api/admin/kyc/{$profile->id}", ['status' => 'approved'])->assertOk();
        $this->actingAs($admin)->patchJson("/api/admin/vehicles/{$vehicle->id}", ['status' => 'approved'])->assertOk();

        $this->assertDatabaseHas('activity_logs', ['action' => 'seller_kyc.submitted', 'category' => 'verification']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'seller_kyc.approved', 'actor_id' => $admin->id, 'actor_role' => 'admin']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'vehicle.approved', 'actor_id' => $admin->id]);

        // Data yang sudah terverifikasi tetap terlihat di tabel verifikasi (status=all).
        $this->actingAs($admin)->getJson('/api/admin/kyc?status=all')->assertOk()->assertJsonPath('data.0.status', 'approved');

        $this->actingAs($admin)->getJson('/api/admin/activity-logs?category=verification')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'verification')
            ->assertJsonFragment(['description' => 'KYC penjual Sari Penjual disetujui']);
    }

    public function test_transaction_activity_has_timeline_and_system_actor_for_auto_approval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$buyer, , $transaction] = $this->checkout(['payment_scheme' => 'credit', 'dp_percent' => 20, 'tenor_months' => 12, 'insurance_type' => 'all_risk']);
        $this->actingAs($buyer)->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay", self::CARD_PAYMENT)->assertOk();

        $this->travel(61)->seconds();
        $this->actingAs($buyer)->getJson("/api/buyer/transactions/{$transaction->id}")->assertOk();

        $timeline = $this->actingAs($admin)->getJson("/api/admin/activity-logs?transaction_id={$transaction->id}&per_page=100")->assertOk();
        $actions = collect($timeline->json('data'))->pluck('action');

        foreach (['transaction.checkout', 'payment.paid', 'escrow.escrow_hold', 'loan.submitted', 'loan.approved', 'document.generated'] as $action) {
            $this->assertContains($action, $actions, "Aksi {$action} tidak tercatat");
        }

        $this->assertNull(ActivityLog::query()->where('action', 'loan.approved')->value('actor_id'));
        $this->assertSame($buyer->id, ActivityLog::query()->where('action', 'transaction.checkout')->value('actor_id'));

        $this->actingAs($buyer)->getJson('/api/admin/activity-logs')->assertForbidden();
    }

    public function test_payout_grants_sale_bonus_and_tier_bonus_on_third_sale(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        foreach ([1, 2, 3] as $i) {
            $vehicle = Vehicle::factory()->create(['seller_id' => $seller->id, 'status' => VehicleStatus::Sold, 'price' => 200_000_000]);
            $transaction = Transaction::factory()->payoutRelease()->for($vehicle)->create([
                'seller_id' => $seller->id,
                'vehicle_price' => 200_000_000,
                'amount' => 40_000_000,
            ]);
            $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse", ['reference' => "TRF-{$i}"])->assertOk();
        }

        $this->assertSame(3, SellerBonus::query()->where('type', 'sale')->count());
        $this->assertEquals(1_000_000, (float) SellerBonus::query()->where('type', 'sale')->value('amount'));
        $this->assertDatabaseHas('seller_bonuses', ['type' => 'tier', 'tier_threshold' => 3, 'amount' => 2_500_000]);
        $this->assertDatabaseHas('transaction_payouts', ['reference' => 'TRF-3', 'bonus_amount' => 3_500_000]);

        $this->actingAs($seller)->getJson('/api/seller/bonuses')
            ->assertOk()
            ->assertJsonPath('summary.completed_sales', 3)
            ->assertJsonPath('summary.next_tier.threshold', 5)
            ->assertJsonPath('summary.next_tier.remaining_sales', 2)
            ->assertJsonCount(4, 'data');
    }

    public function test_auto_promo_shows_strikethrough_price_and_discounts_checkout_without_reducing_seller_share(): void
    {
        Promo::factory()->create(['name' => 'Flash Sale Oktober', 'badge' => 'Flash Sale', 'discount_type' => 'percent', 'discount_value' => 2, 'max_discount' => 5_000_000]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 400_000_000]);

        $this->getJson("/api/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.promo.discount_amount', 5_000_000)
            ->assertJsonPath('data.promo.final_price', 395_000_000)
            ->assertJsonPath('data.promo.badge', 'Flash Sale');

        $breakdown = $this->getJson("/api/vehicles/{$vehicle->id}/quote?".http_build_query([
            'payment_scheme' => 'down_payment', 'dp_percent' => 10, 'insurance_type' => 'none',
        ]))->assertOk()->json('data.breakdown');

        $this->assertEquals(5_000_000, $breakdown['promotions']['due_now_saving']);
        $this->assertEquals(40_000_000 + $breakdown['registration']['total'] - 5_000_000, $breakdown['due_now']['total']);
        $this->assertEquals(400_000_000 - 1_200_000, $breakdown['seller_settlement']['total_net']);
    }

    public function test_voucher_codes_validate_and_apply_free_addon_or_credit_rate(): void
    {
        Promo::factory()->create(['kind' => 'voucher', 'code' => 'GRATISBANJIR', 'benefit' => 'free_addon', 'discount_type' => null, 'discount_value' => null, 'addon_code' => 'flood']);
        Promo::factory()->create(['kind' => 'voucher', 'code' => 'BUNGA2', 'benefit' => 'credit_rate', 'discount_type' => null, 'discount_value' => null, 'rate_cut' => 0.02]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 400_000_000, 'year' => now()->year - 2]);
        $quote = fn (array $params) => $this->getJson("/api/vehicles/{$vehicle->id}/quote?".http_build_query($params));

        $quote(['payment_scheme' => 'down_payment', 'dp_percent' => 10, 'insurance_type' => 'none', 'promo_code' => 'TIDAKADA'])
            ->assertUnprocessable()->assertJsonValidationErrors('promo_code');
        $quote(['payment_scheme' => 'down_payment', 'dp_percent' => 10, 'insurance_type' => 'all_risk', 'promo_code' => 'BUNGA2'])
            ->assertUnprocessable()->assertJsonValidationErrors('promo_code');

        $addon = $quote(['payment_scheme' => 'down_payment', 'dp_percent' => 10, 'insurance_type' => 'all_risk', 'promo_code' => 'gratisbanjir'])->assertOk()->json('data.breakdown');
        $this->assertContains('flood', $addon['insurance']['addons']);
        $this->assertEquals(300_000, $addon['promotions']['due_now_saving']);

        $credit = $quote(['payment_scheme' => 'credit', 'dp_percent' => 20, 'tenor_months' => 36, 'insurance_type' => 'all_risk', 'promo_code' => 'BUNGA2'])->assertOk()->json('data.breakdown');
        $this->assertEqualsWithDelta(0.08, $credit['credit']['flat_rate'], 0.0001);
        $this->assertGreaterThan(0, $credit['promotions']['total_saving']);
    }

    public function test_checkout_redeems_promo_and_counts_quota(): void
    {
        $promo = Promo::factory()->create(['kind' => 'voucher', 'code' => 'HEMAT5', 'discount_type' => 'fixed', 'discount_value' => 5_000_000, 'max_discount' => null, 'quota' => 1]);

        [, , $transaction] = $this->checkout(['payment_scheme' => 'down_payment', 'dp_percent' => 10, 'insurance_type' => 'none', 'promo_code' => 'HEMAT5']);

        $this->assertSame('HEMAT5', $transaction->promo_code);
        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertDatabaseHas('promo_redemptions', ['promo_id' => $promo->id, 'transaction_id' => $transaction->id, 'benefit_amount' => 5_000_000]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'promo.redeemed', 'transaction_id' => $transaction->id]);

        // Kuota habis → voucher tidak bisa dipakai lagi.
        $other = Vehicle::factory()->approved()->create(['price' => 300_000_000]);
        $this->getJson("/api/vehicles/{$other->id}/quote?".http_build_query(['payment_scheme' => 'down_payment', 'dp_percent' => 10, 'insurance_type' => 'none', 'promo_code' => 'HEMAT5']))
            ->assertUnprocessable();
    }

    public function test_admin_manages_promos_and_cannot_delete_used_promo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->postJson('/api/admin/promos', ['name' => 'Voucher tanpa kode', 'kind' => 'voucher', 'benefit' => 'discount', 'discount_type' => 'percent', 'discount_value' => 80])
            ->assertUnprocessable()->assertJsonValidationErrors(['code', 'discount_value']);

        $id = $this->actingAs($admin)->postJson('/api/admin/promos', [
            'name' => 'Bunga Spesial', 'kind' => 'voucher', 'code' => 'kredit1', 'benefit' => 'credit_rate', 'rate_cut_percent' => 1.5, 'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'KREDIT1')->assertJsonPath('data.rate_cut', '0.0150')->json('data.id');

        $this->actingAs($admin)->deleteJson("/api/admin/promos/{$id}")->assertNoContent();

        $used = Promo::factory()->create();
        [, , $transaction] = $this->checkout(['payment_scheme' => 'down_payment', 'dp_percent' => 10, 'insurance_type' => 'none']);
        $this->assertDatabaseHas('promo_redemptions', ['promo_id' => $used->id, 'transaction_id' => $transaction->id]);
        $this->actingAs($admin)->deleteJson("/api/admin/promos/{$used->id}")->assertConflict();
    }

    public function test_report_summarises_sales_and_tables(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$buyer, , $transaction] = $this->checkout(['payment_scheme' => 'down_payment', 'dp_percent' => 10, 'insurance_type' => 'none']);
        $this->actingAs($buyer)->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay", self::CARD_PAYMENT)->assertOk();

        $this->actingAs($admin)->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonPath('kpis.transactions_paid', 1)
            ->assertJsonPath('kpis.gmv', 300_000_000)
            ->assertJsonCount(1, 'tables.sales')
            ->assertJsonCount(12, 'monthly')
            ->assertJsonPath('payment_schemes.0.count', 1);

        $this->actingAs($buyer)->getJson('/api/admin/reports')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{0: User, 1: User, 2: Transaction}
     */
    private function checkout(array $payment): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller, 'ktp_number' => '3171000000000001', 'ktp_name' => 'Sari Penjual']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'ktp_number' => '3171000000000002', 'ktp_name' => 'Budi Pembeli']);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 300_000_000, 'year' => now()->year - 2, 'seller_id' => $seller->id]);

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            ...$payment,
            'buyer_address' => 'Jl. Melati No. 10, Jakarta Selatan',
            'buyer_phone' => '081234567890',
        ])->assertCreated();

        return [$buyer, $seller, Transaction::query()->latest('id')->firstOrFail()->load('loan')];
    }
}
