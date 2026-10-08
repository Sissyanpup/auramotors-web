<?php

namespace Tests\Feature;

use App\Documents\DocumentGenerator;
use App\Enums\EscrowStatus;
use App\Enums\TransactionDocumentType;
use App\Enums\TransactionPaymentStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\SignedDocumentUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_invoice_generated_at_checkout(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890001',
            'ktp_name' => 'Buyer Doc',
        ]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 200_000_000]);

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'payment_scheme' => 'full',
            'dp_percent' => 100,
            'insurance_type' => 'none',
            'buyer_address' => 'Jl. Contoh No. 1',
            'buyer_phone' => '08123456789',
        ])->assertCreated();

        $transaction = Transaction::firstOrFail();
        $this->assertDatabaseHas('transaction_documents', [
            'transaction_id' => $transaction->id,
            'type' => TransactionDocumentType::CommercialInvoice->value,
        ]);
    }

    public function test_deposit_receipt_generated_when_paid_via_mock(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890002',
            'ktp_name' => 'Buyer Deposit',
        ]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'payment_scheme' => 'full',
            'dp_percent' => 100,
            'insurance_type' => 'none',
            'buyer_address' => 'Jl. Contoh No. 1',
            'buyer_phone' => '08123456789',
        ])->assertCreated();

        $transaction = Transaction::firstOrFail();

        $this->actingAs($buyer)->postJson("/api/payments/mock/{$transaction->gateway_reference}/pay")
            ->assertOk();

        $this->assertDatabaseHas('transaction_documents', [
            'transaction_id' => $transaction->id,
            'type' => TransactionDocumentType::DepositReceipt->value,
        ]);
    }

    public function test_bill_of_sale_generated_on_admin_approve_handover(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890003',
            'ktp_name' => 'Buyer BoS',
        ]);
        $vehicle = Vehicle::factory()->approved()->create(['seller_id' => $seller->id, 'price' => 100_000_000]);

        $transaction = Transaction::factory()->for($vehicle)->create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'payment_status' => TransactionPaymentStatus::Paid,
            'escrow_status' => EscrowStatus::EscrowHold,
            'buyer_confirmed_at' => now(),
            'seller_confirmed_at' => now(),
            'amount' => 100_000_000,
            'vehicle_price' => 100_000_000,
        ]);

        $this->actingAs($admin)->postJson("/api/admin/transactions/{$transaction->id}/approve-handover", [
            'note' => 'Serah-terima OK',
        ])->assertOk();

        $this->assertDatabaseHas('transaction_documents', [
            'transaction_id' => $transaction->id,
            'type' => TransactionDocumentType::BillOfSale->value,
        ]);
    }

    public function test_document_download_via_signed_url(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890004',
            'ktp_name' => 'Buyer DL',
        ]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'payment_scheme' => 'full',
            'dp_percent' => 100,
            'insurance_type' => 'none',
            'buyer_address' => 'Jl. Contoh No. 1',
            'buyer_phone' => '08123456789',
        ])->assertCreated();

        $transaction = Transaction::with('documents')->firstOrFail();
        $doc = $transaction->documents->first();
        $this->assertNotNull($doc);

        // Signed URL: generate via helper yang sama dengan resource layer.
        $url = SignedDocumentUrl::for('transaction-documents.show', [$transaction->id, $doc->id]);

        // Signed URL bisa diakses tanpa sesi (browser buka di tab baru).
        $response = $this->get($url);
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_document_download_unsigned_url_rejected(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890005',
            'ktp_name' => 'Buyer Unsigned',
        ]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'payment_scheme' => 'full',
            'dp_percent' => 100,
            'insurance_type' => 'none',
            'buyer_address' => 'Jl. Contoh No. 1',
            'buyer_phone' => '08123456789',
        ])->assertCreated();

        $transaction = Transaction::with('documents')->firstOrFail();
        $doc = $transaction->documents->first();

        // Tanpa signature → middleware `signed` tolak 403.
        $this->get("/api/transactions/{$transaction->id}/documents/{$doc->id}")
            ->assertForbidden();
    }

    public function test_regenerating_document_replaces_previous_file(): void
    {
        Storage::fake('local');

        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890007',
            'ktp_name' => 'Buyer Regen',
        ]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);
        $transaction = Transaction::factory()->for($vehicle)->create([
            'buyer_id' => $buyer->id,
            'seller_id' => $vehicle->seller_id,
            'amount' => 100_000_000,
            'vehicle_price' => 100_000_000,
        ]);

        $generator = app(DocumentGenerator::class);
        $first = $generator->generateCommercialInvoice($transaction);
        $second = $generator->generateCommercialInvoice($transaction);

        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseMissing('transaction_documents', ['id' => $first->id]);
        $this->assertDatabaseHas('transaction_documents', ['id' => $second->id]);
    }
}
