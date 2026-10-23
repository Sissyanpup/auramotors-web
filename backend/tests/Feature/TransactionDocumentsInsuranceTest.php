<?php

namespace Tests\Feature;

use App\Enums\TransactionDocumentType;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sprint 10: rincian harga, asuransi + perluasan, portal bank simulasi, surat
 * bank/asuransi, dan tanda tangan elektronik per dokumen.
 */
class TransactionDocumentsInsuranceTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==';

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
        config(['payout.commission_rate' => 0.03]);
    }

    public function test_quote_explains_every_component_of_the_price(): void
    {
        $vehicle = Vehicle::factory()->approved()->create(['price' => 400_000_000]);

        $response = $this->getJson("/api/vehicles/{$vehicle->id}/quote?".http_build_query([
            'payment_scheme' => 'down_payment',
            'dp_percent' => 10,
            'insurance_type' => 'all_risk',
            'insurance_addons' => ['flood', 'towing_replacement'],
        ]));

        $response->assertOk()
            ->assertJsonPath('data.breakdown.payment.dp_amount', 40_000_000)
            ->assertJsonPath('data.breakdown.insurance.total', 11_110_000)
            ->assertJsonPath('data.breakdown.due_now.total', 60_828_000)
            ->assertJsonPath('data.breakdown.registration.in_escrow', true)
            ->assertJsonPath('data.breakdown.registration.total', 9_718_000)
            ->assertJsonPath('data.breakdown.seller_settlement.commission', 1_200_000)
            ->assertJsonPath('data.breakdown.seller_settlement.total_net', 398_800_000)
            ->assertJsonPath('data.breakdown.credit_simulation.applicable', true)
            ->assertJsonCount(5, 'data.breakdown.credit_simulation.rows');

        $this->assertContains('towing_replacement', array_column($response->json('data.available_addons'), 'code'));
    }

    public function test_quote_rejects_addon_not_available_for_tlo(): void
    {
        $vehicle = Vehicle::factory()->approved()->create(['price' => 400_000_000]);

        $this->getJson("/api/vehicles/{$vehicle->id}/quote?".http_build_query([
            'payment_scheme' => 'down_payment',
            'dp_percent' => 10,
            'insurance_type' => 'tlo',
            'insurance_addons' => ['towing_replacement'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('insurance_addons.0');
    }

    public function test_checkout_snapshots_breakdown_and_issues_contract_and_insurance_application(): void
    {
        [$buyer, $transaction] = $this->checkoutWithInsurance();

        // DP 40jt + asuransi 11,11jt + balik nama 9,718jt.
        $this->assertSame('60828000.00', $transaction->amount);
        $this->assertSame('11110000.00', $transaction->insurance_premium);
        $this->assertSame(['flood', 'towing_replacement'], $transaction->insurance_addons);
        $this->assertSame(60_828_000, (int) $transaction->price_breakdown['due_now']['total']);

        foreach ([TransactionDocumentType::CommercialInvoice, TransactionDocumentType::SalesPurchaseAgreement, TransactionDocumentType::PriceBreakdown, TransactionDocumentType::InsuranceApplication] as $type) {
            $this->assertDatabaseHas('transaction_documents', ['transaction_id' => $transaction->id, 'type' => $type->value]);
        }

        $this->actingAs($buyer)->getJson("/api/buyer/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.price_breakdown.insurance.total', 11_110_000)
            ->assertJsonPath('data.signing_checklist.0.type', 'sales_purchase_agreement')
            ->assertJsonPath('data.signing_checklist.1.type', 'insurance_application');
    }

    public function test_bank_portal_card_payment_stores_only_masked_card_and_issues_bank_and_insurance_documents(): void
    {
        [$buyer, $transaction] = $this->checkoutWithInsurance();

        $this->actingAs($buyer)
            ->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay", self::CARD_PAYMENT)
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment_channel', 'VISA')
            ->assertJsonPath('data.payment_account_masked', '•••• •••• •••• 4444');

        $fresh = $transaction->fresh();
        $this->assertNotNull($fresh->payment_approval_code);
        $this->assertStringStartsWith('POL/AURA/', $fresh->insurance_policy_number);
        $this->assertDatabaseMissing('transactions', ['payment_account_masked' => '4111 2222 3333 4444']);

        foreach ([
            TransactionDocumentType::BankPaymentAdvice,
            TransactionDocumentType::EscrowFundConfirmation,
            TransactionDocumentType::InsurancePolicy,
            TransactionDocumentType::SellerOwnershipStatement,
            TransactionDocumentType::OwnershipTransferPowerOfAttorney,
            TransactionDocumentType::HandoverMinutes,
            TransactionDocumentType::SaleReceipt,
        ] as $type) {
            $this->assertDatabaseHas('transaction_documents', ['transaction_id' => $transaction->id, 'type' => $type->value]);
        }
    }

    public function test_bank_portal_requires_six_digit_otp(): void
    {
        [$buyer, $transaction] = $this->checkoutWithInsurance();

        $this->actingAs($buyer)
            ->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay", [...self::CARD_PAYMENT, 'otp' => '12'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('otp');

        $this->assertSame('pending', $transaction->fresh()->payment_status->value);
    }

    public function test_paypal_payment_masks_email(): void
    {
        [$buyer, $transaction] = $this->checkoutWithInsurance();

        $this->actingAs($buyer)
            ->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay", [
                'method' => 'paypal',
                'paypal_email' => 'budi.pembeli@contoh.test',
                'paypal_password' => 'rahasia',
                'otp' => '000000',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_channel', 'PAYPAL')
            ->assertJsonPath('data.payment_account_masked', 'bu**********@contoh.test');
    }

    public function test_buyer_and_seller_sign_their_own_documents_in_batch(): void
    {
        [$buyer, $transaction] = $this->checkoutWithInsurance();
        $this->actingAs($buyer)->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay", self::CARD_PAYMENT)->assertOk();
        $seller = $transaction->seller;

        $this->actingAs($buyer)->postJson("/api/transactions/{$transaction->id}/document-signatures", [
            'document_types' => ['insurance_application', 'handover_minutes', 'ownership_transfer_poa'],
            'signature' => self::SIGNATURE,
        ])->assertOk();

        $this->actingAs($seller)->postJson("/api/transactions/{$transaction->id}/document-signatures", [
            'document_types' => ['handover_minutes', 'sale_receipt'],
            'signature' => self::SIGNATURE,
        ])->assertOk();

        $this->assertDatabaseCount('transaction_document_signatures', 5);
        $this->assertDatabaseHas('transaction_document_signatures', [
            'transaction_id' => $transaction->id, 'document_type' => 'handover_minutes', 'role' => 'seller', 'user_id' => $seller->id,
        ]);

        $checklist = collect($this->actingAs($seller)->getJson("/api/seller/transactions/{$transaction->id}")->json('data.signing_checklist'))
            ->keyBy('type');
        $this->assertTrue($checklist['handover_minutes']['is_complete']);
        $this->assertFalse($checklist['ownership_transfer_poa']['is_complete']);
    }

    public function test_party_cannot_sign_document_meant_for_the_other_party(): void
    {
        [$buyer, $transaction] = $this->checkoutWithInsurance();

        $this->actingAs($transaction->seller)->postJson("/api/transactions/{$transaction->id}/document-signatures", [
            'document_types' => ['insurance_application'],
            'signature' => self::SIGNATURE,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('transaction_document_signatures', 0);
    }

    public function test_handover_documents_cannot_be_signed_before_payment(): void
    {
        [$buyer, $transaction] = $this->checkoutWithInsurance();

        $this->actingAs($buyer)->postJson("/api/transactions/{$transaction->id}/document-signatures", [
            'document_types' => ['handover_minutes'],
            'signature' => self::SIGNATURE,
        ])->assertConflict();
    }

    public function test_signing_twice_is_rejected(): void
    {
        [$buyer, $transaction] = $this->checkoutWithInsurance();
        $payload = ['document_types' => ['insurance_application'], 'signature' => self::SIGNATURE];

        $this->actingAs($buyer)->postJson("/api/transactions/{$transaction->id}/document-signatures", $payload)->assertOk();
        $this->actingAs($buyer)->postJson("/api/transactions/{$transaction->id}/document-signatures", $payload)->assertConflict();
    }

    public function test_payout_excludes_insurance_premium_from_seller_funds(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->payoutRelease()->create([
            'amount' => 51_110_000,
            'insurance_premium' => 11_110_000,
        ]);

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse", ['reference' => 'TRF-1'])
            ->assertOk()
            ->assertJsonPath('data.payouts.0.commission_amount', '1200000.00')
            ->assertJsonPath('data.payouts.0.payout_amount', '38800000.00');
    }

    public function test_payout_excludes_registration_fee_held_in_escrow(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [, $transaction] = $this->checkoutWithInsurance();
        $transaction->update([
            'payment_status' => 'paid',
            'escrow_status' => 'payout_release',
            'buyer_confirmed_at' => now(),
            'seller_confirmed_at' => now(),
        ]);

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/disburse", ['reference' => 'TRF-2'])
            ->assertOk()
            ->assertJsonPath('data.payouts.0.commission_amount', '1200000.00')
            ->assertJsonPath('data.payouts.0.payout_amount', '38800000.00');
    }

    /**
     * @return array{0: User, 1: Transaction}
     */
    private function checkoutWithInsurance(): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller, 'ktp_number' => '3171000000000001', 'ktp_name' => 'Sari Penjual']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'ktp_number' => '3171000000000002', 'ktp_name' => 'Budi Pembeli']);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 400_000_000, 'seller_id' => $seller->id, 'vin' => 'JTDBR32E720123456']);

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'payment_scheme' => 'down_payment',
            'dp_percent' => 10,
            'insurance_type' => 'all_risk',
            'insurance_addons' => ['flood', 'towing_replacement'],
            'buyer_address' => 'Jl. Melati No. 10, Jakarta Selatan',
            'buyer_phone' => '081234567890',
        ])->assertCreated();

        return [$buyer, Transaction::firstOrFail()];
    }
}
