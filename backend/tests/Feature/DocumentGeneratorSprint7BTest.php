<?php

namespace Tests\Feature;

use App\Enums\EscrowStatus;
use App\Enums\PayoutMethod;
use App\Enums\TransactionDocumentType;
use App\Enums\TransactionPaymentStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentGeneratorSprint7BTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_CHECKOUT = [
        'payment_scheme' => 'full',
        'dp_percent' => 100,
        'insurance_type' => 'none',
        'buyer_address' => 'Jl. Contoh No. 1',
        'buyer_phone' => '08123456789',
    ];

    private function makeBuyer(string $suffix = '0001'): User
    {
        return User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '320123456789'.$suffix,
            'ktp_name' => 'Buyer '.$suffix,
        ]);
    }

    public function test_sales_purchase_agreement_generated_at_checkout(): void
    {
        $buyer = $this->makeBuyer('7B01');
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)
            ->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", self::VALID_CHECKOUT)
            ->assertCreated();

        $transaction = Transaction::firstOrFail();
        $this->assertDatabaseHas('transaction_documents', [
            'transaction_id' => $transaction->id,
            'type' => TransactionDocumentType::SalesPurchaseAgreement->value,
        ]);
    }

    public function test_tax_invoice_generated_at_payment_paid(): void
    {
        $buyer = $this->makeBuyer('7B02');
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)
            ->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", self::VALID_CHECKOUT)
            ->assertCreated();

        $transaction = Transaction::firstOrFail();
        $this->actingAs($buyer)
            ->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay")
            ->assertOk();

        $this->assertDatabaseHas('transaction_documents', [
            'transaction_id' => $transaction->id,
            'type' => TransactionDocumentType::TaxInvoice->value,
        ]);
    }

    public function test_escrow_disbursement_note_generated_after_admin_disburse(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => 'approved',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder_name' => 'PENJUAL A',
        ]);

        $buyer = $this->makeBuyer('7B03');
        $vehicle = Vehicle::factory()->approved()->create(['seller_id' => $seller->id, 'price' => 100_000_000]);

        $transaction = Transaction::factory()->for($vehicle)->create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'payment_status' => TransactionPaymentStatus::Paid,
            'escrow_status' => EscrowStatus::PayoutRelease,
            'buyer_confirmed_at' => now(),
            'seller_confirmed_at' => now(),
            'amount' => 100_000_000,
            'vehicle_price' => 100_000_000,
        ]);

        // Force manual payout driver supaya tidak butuh network.
        config(['payout.driver' => PayoutMethod::Manual->value]);

        $this->actingAs($admin)
            ->postJson("/api/admin/transactions/{$transaction->id}/disburse", [
                'reference' => 'TRX-BANK-REF-999',
                'note' => 'Payout manual OK',
            ])
            ->assertOk();

        $this->assertDatabaseHas('transaction_documents', [
            'transaction_id' => $transaction->id,
            'type' => TransactionDocumentType::EscrowDisbursementNote->value,
        ]);
    }

    public function test_buyer_can_sign_transaction_and_regenerates_spa(): void
    {
        Storage::fake('local');
        $buyer = $this->makeBuyer('7B04');
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)
            ->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", self::VALID_CHECKOUT)
            ->assertCreated();

        $transaction = Transaction::firstOrFail();
        $onePxPng = base64_encode(hex2bin('89504E470D0A1A0A0000000D49484452000000010000000108060000001F15C4890000000D49444154789C6300010000000500010D0A2DB40000000049454E44AE426082'));

        $response = $this->actingAs($buyer)
            ->postJson("/api/transactions/{$transaction->id}/sign", [
                'signature' => 'data:image/png;base64,'.$onePxPng,
            ]);

        $response->assertOk();
        $fresh = $transaction->fresh();
        $this->assertNotNull($fresh->buyer_signature_path);
        $this->assertNotNull($fresh->buyer_signed_at);
        $this->assertTrue(Storage::disk('local')->exists($fresh->buyer_signature_path));
    }

    public function test_double_sign_rejected(): void
    {
        Storage::fake('local');
        $buyer = $this->makeBuyer('7B05');
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)
            ->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", self::VALID_CHECKOUT)
            ->assertCreated();

        $transaction = Transaction::firstOrFail();
        $onePxPng = 'data:image/png;base64,'.base64_encode(hex2bin('89504E470D0A1A0A0000000D49484452000000010000000108060000001F15C4890000000D49444154789C6300010000000500010D0A2DB40000000049454E44AE426082'));

        $this->actingAs($buyer)->postJson("/api/transactions/{$transaction->id}/sign", ['signature' => $onePxPng])->assertOk();
        $this->actingAs($buyer)->postJson("/api/transactions/{$transaction->id}/sign", ['signature' => $onePxPng])->assertConflict();
    }

    public function test_third_party_cannot_sign(): void
    {
        $buyer = $this->makeBuyer('7B06');
        $intruder = $this->makeBuyer('7B07');
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)
            ->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", self::VALID_CHECKOUT)
            ->assertCreated();

        $transaction = Transaction::firstOrFail();
        $onePxPng = 'data:image/png;base64,'.base64_encode(hex2bin('89504E470D0A1A0A0000000D49484452000000010000000108060000001F15C4890000000D49444154789C6300010000000500010D0A2DB40000000049454E44AE426082'));

        // Buyer lain yang bukan pihak transaksi → policy view menolak.
        $this->actingAs($intruder)
            ->postJson("/api/transactions/{$transaction->id}/sign", ['signature' => $onePxPng])
            ->assertForbidden();
    }

    public function test_invalid_signature_payload_rejected(): void
    {
        $buyer = $this->makeBuyer('7B08');
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)
            ->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", self::VALID_CHECKOUT)
            ->assertCreated();

        $transaction = Transaction::firstOrFail();

        // Payload bukan data URL PNG.
        $this->actingAs($buyer)
            ->postJson("/api/transactions/{$transaction->id}/sign", ['signature' => 'bukan-data-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature');
    }
}
