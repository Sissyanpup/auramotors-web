<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\LoanInstallment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sprint 11: kredit leasing sebagai metode bayar, jadwal angsuran, denda,
 * dan pengelolaan cicilan oleh admin.
 */
class CreditLoanTest extends TestCase
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
        config(['payout.commission_rate' => 0.03, 'pricing.credit.auto_approve_seconds' => 60]);
    }

    public function test_credit_quote_explains_rate_installment_and_upfront_costs(): void
    {
        $vehicle = Vehicle::factory()->approved()->create(['price' => 400_000_000, 'year' => now()->year - 3]);

        $response = $this->getJson("/api/vehicles/{$vehicle->id}/quote?".http_build_query([
            'payment_scheme' => 'credit',
            'dp_percent' => 20,
            'tenor_months' => 36,
            'insurance_type' => 'all_risk',
        ]))->assertOk();

        $breakdown = $response->json('data.breakdown');
        $credit = $breakdown['credit'];
        $annualInsurance = $breakdown['insurance']['total'];

        $this->assertEqualsWithDelta(0.10, $credit['flat_rate'], 0.0001);
        $this->assertSame(0.0, (float) $credit['age_surcharge_rate']);
        $this->assertEquals(320_000_000, $credit['vehicle_financed']);
        $this->assertEquals($annualInsurance * 2, $credit['insurance_financed']);
        $this->assertEquals($credit['monthly_installment'] * 36, $credit['total_payable']);
        $this->assertGreaterThan($credit['flat_rate'], $credit['effective_rate']);
        $this->assertSame('lender', $breakdown['seller_settlement']['remaining_payer']);

        $expectedDueNow = 80_000_000 + $annualInsurance + $breakdown['registration']['total'] + $credit['upfront']['total'];
        $this->assertEquals($expectedDueNow, $breakdown['due_now']['total']);
    }

    public function test_older_vehicle_gets_age_surcharge(): void
    {
        $vehicle = Vehicle::factory()->approved()->create(['price' => 300_000_000, 'year' => now()->year - 12]);

        $this->getJson("/api/vehicles/{$vehicle->id}/quote?".http_build_query([
            'payment_scheme' => 'credit',
            'dp_percent' => 30,
            'tenor_months' => 36,
            'insurance_type' => 'tlo',
        ]))->assertOk()->assertJsonPath('data.breakdown.credit.age_surcharge_rate', 0.02);
    }

    public function test_credit_requires_leasing_dp_tenor_and_insurance(): void
    {
        $vehicle = Vehicle::factory()->approved()->create(['price' => 300_000_000]);

        $this->getJson("/api/vehicles/{$vehicle->id}/quote?".http_build_query([
            'payment_scheme' => 'credit',
            'dp_percent' => 10,
            'insurance_type' => 'none',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['dp_percent', 'tenor_months', 'insurance_type']);
    }

    public function test_paid_credit_checkout_opens_application_that_auto_approves_after_delay(): void
    {
        [$buyer, $seller, $transaction] = $this->paidCreditTransaction();

        $loan = $transaction->loan;
        $this->assertSame(LoanStatus::Submitted, $loan->status);
        $this->assertEquals($transaction->price_breakdown['credit']['monthly_installment'], $loan->monthly_installment);

        $this->actingAs($buyer)->getJson("/api/buyer/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.loan.status', 'submitted')
            ->assertJsonFragment(['key' => 'credit_approved', 'label' => 'Kredit disetujui leasing & sisa harga dilunasi ke penjual', 'is_met' => false]);

        $this->travel(61)->seconds();

        $this->actingAs($seller)->getJson("/api/seller/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.loan.status', 'approved')
            ->assertJsonCount(12, 'data.loan.installments')
            ->assertJsonFragment(['key' => 'credit_approved', 'label' => 'Kredit disetujui leasing & sisa harga dilunasi ke penjual', 'is_met' => true]);

        $this->assertNotNull($loan->fresh()->lender_disbursed_at);
        $this->assertEquals((float) $loan->principal, (float) LoanInstallment::sum('principal_amount'));
    }

    public function test_buyer_pays_installments_in_order_through_bank_portal(): void
    {
        [$buyer, , $transaction] = $this->paidCreditTransaction();
        $loan = $this->approve($transaction->loan);
        [$first, $second] = $loan->installments->take(2)->all();

        $this->actingAs($buyer)->postJson("/api/buyer/installments/{$second->id}/pay", self::CARD_PAYMENT)
            ->assertUnprocessable();

        $this->actingAs($buyer)->postJson("/api/buyer/installments/{$first->id}/pay", self::CARD_PAYMENT)
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_channel', 'VISA')
            ->assertJsonPath('data.payment_account_masked', '•••• •••• •••• 4444');

        $intruder = User::factory()->create(['role' => UserRole::Buyer]);
        $this->actingAs($intruder)->postJson("/api/buyer/installments/{$second->id}/pay", self::CARD_PAYMENT)
            ->assertForbidden();
    }

    public function test_advancing_simulated_clock_makes_installment_overdue_with_late_fee(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [, , $transaction] = $this->paidCreditTransaction();
        $loan = $this->approve($transaction->loan);
        $first = $loan->installments->first();

        $this->actingAs($admin)->postJson("/api/admin/loans/{$loan->id}/advance-clock")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/loans/{$loan->id}/advance-clock")->assertOk();

        $response = $this->actingAs($admin)->getJson('/api/admin/loans?filter=overdue')
            ->assertOk()
            ->assertJsonPath('stats.overdue_loans', 1)
            ->assertJsonPath('data.0.installments.0.status', 'overdue');

        $daysLate = $response->json('data.0.installments.0.days_late');
        $expectedFee = round((float) $first->amount * 0.002 * $daysLate);
        $this->assertGreaterThan(0, $daysLate);
        $this->assertEquals($expectedFee, $response->json('data.0.installments.0.late_fee'));

        $this->actingAs($admin)->postJson("/api/admin/installments/{$first->id}/record-payment", ['channel' => 'Tunai — kantor leasing'])
            ->assertOk()
            ->assertJsonPath('data.installments.0.status', 'paid');

        $this->assertEquals((float) $first->amount + $expectedFee, (float) $first->fresh()->paid_amount);
    }

    public function test_loan_becomes_paid_off_after_last_installment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [, , $transaction] = $this->paidCreditTransaction();
        $loan = $this->approve($transaction->loan);

        foreach ($loan->installments as $installment) {
            $this->actingAs($admin)->postJson("/api/admin/installments/{$installment->id}/record-payment", ['channel' => 'Transfer'])->assertOk();
        }

        $this->assertSame(LoanStatus::PaidOff, $loan->fresh()->status);
        $this->actingAs($admin)->postJson("/api/admin/loans/{$loan->id}/advance-clock")->assertConflict();
    }

    public function test_non_admin_cannot_manage_loans(): void
    {
        [$buyer, , $transaction] = $this->paidCreditTransaction();

        $this->actingAs($buyer)->getJson('/api/admin/loans')->assertForbidden();
        $this->actingAs($buyer)->postJson("/api/admin/loans/{$transaction->loan->id}/approve")->assertForbidden();
    }

    private function approve(VehicleLoan $loan): VehicleLoan
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->postJson("/api/admin/loans/{$loan->id}/approve")->assertOk();

        return $loan->fresh('installments');
    }

    /**
     * @return array{0: User, 1: User, 2: Transaction}
     */
    private function paidCreditTransaction(): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller, 'ktp_number' => '3171000000000001', 'ktp_name' => 'Sari Penjual']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'ktp_number' => '3171000000000002', 'ktp_name' => 'Budi Pembeli']);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 300_000_000, 'year' => now()->year - 2, 'seller_id' => $seller->id]);

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'payment_scheme' => 'credit',
            'dp_percent' => 20,
            'tenor_months' => 12,
            'insurance_type' => 'all_risk',
            'buyer_address' => 'Jl. Melati No. 10, Jakarta Selatan',
            'buyer_phone' => '081234567890',
        ])->assertCreated();

        $transaction = Transaction::firstOrFail();
        $this->actingAs($buyer)->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay", self::CARD_PAYMENT)->assertOk();

        return [$buyer, $seller, $transaction->fresh('loan')];
    }
}
