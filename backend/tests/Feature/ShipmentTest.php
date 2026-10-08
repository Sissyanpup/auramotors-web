<?php

namespace Tests\Feature;

use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    private function transactionOwnedBy(User $buyer, ?User $seller = null): Transaction
    {
        $seller ??= User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = Vehicle::factory()->create(['seller_id' => $seller->id]);

        return Transaction::factory()->for($vehicle)->create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 100_000_000,
            'vehicle_price' => 100_000_000,
        ]);
    }

    private function initShipmentPayload(array $overrides = []): array
    {
        return array_merge([
            'origin_country' => 'ID',
            'destination_country' => 'SG',
            'shipping_mode' => 'sea',
            'carrier_name' => 'Global Auto Lines',
            'tracking_number' => 'MSCU1234567',
            'estimated_arrival' => '2026-10-15',
            'cargo_insurer_name' => 'Marine Insure Co',
            'cargo_policy_number' => 'MI-2026-001',
            'cargo_coverage_amount' => 100_000_000,
        ], $overrides);
    }

    public function test_buyer_can_initiate_shipment(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $tx = $this->transactionOwnedBy($buyer);

        $response = $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", $this->initShipmentPayload([
            'cargo_certificate' => UploadedFile::fake()->create('cargo.pdf', 100),
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', ShipmentStatus::Draft->value)
            ->assertJsonPath('data.cargo_insurance.is_complete', true);
    }

    public function test_non_party_cannot_view_shipment(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $intruder = User::factory()->create(['role' => UserRole::Buyer]);
        $tx = $this->transactionOwnedBy($buyer);

        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", $this->initShipmentPayload())->assertCreated();
        $this->actingAs($intruder)->getJson("/api/transactions/{$tx->id}/shipment")->assertForbidden();
    }

    public function test_double_initiate_shipment_rejected(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $tx = $this->transactionOwnedBy($buyer);

        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", $this->initShipmentPayload())->assertCreated();
        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", $this->initShipmentPayload())->assertConflict();
    }

    public function test_seller_uploads_export_declaration(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $tx = $this->transactionOwnedBy($buyer, $seller);
        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", $this->initShipmentPayload())->assertCreated();

        $this->actingAs($seller)->postJson("/api/transactions/{$tx->id}/shipment/documents", [
            'type' => ShipmentDocumentType::ExportDeclaration->value,
            'file' => UploadedFile::fake()->create('export.pdf', 200),
        ])->assertCreated();

        $this->assertDatabaseHas('shipment_documents', [
            'shipment_id' => $tx->fresh()->shipment->id,
            'type' => 'export_declaration',
            'uploaded_by' => $seller->id,
        ]);
    }

    public function test_buyer_cannot_upload_seller_only_doc(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $tx = $this->transactionOwnedBy($buyer, $seller);
        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", $this->initShipmentPayload())->assertCreated();

        // Buyer coba upload Export Declaration (seller-only) → 403.
        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment/documents", [
            'type' => ShipmentDocumentType::ExportDeclaration->value,
            'file' => UploadedFile::fake()->create('x.pdf', 100),
        ])->assertForbidden();
    }

    public function test_full_happy_path_state_machine(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $tx = $this->transactionOwnedBy($buyer, $seller);

        // 1. Init shipment (dengan cargo insurance & certificate).
        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", $this->initShipmentPayload([
            'cargo_certificate' => UploadedFile::fake()->create('cargo.pdf', 100),
        ]))->assertCreated();

        // 2. Seller upload Export Declaration.
        $this->actingAs($seller)->postJson("/api/transactions/{$tx->id}/shipment/documents", [
            'type' => 'export_declaration',
            'file' => UploadedFile::fake()->create('exp.pdf', 100),
        ])->assertCreated();

        // 3. Admin: Draft → LogisticsPrep (butuh insurance + export_declaration ✓).
        $this->actingAs($admin)->postJson("/api/admin/transactions/{$tx->id}/shipment/mark-logistics-prep")
            ->assertOk()
            ->assertJsonPath('data.status', 'logistics_prep');

        // 4. Seller upload Bill of Lading (shipping mode = sea).
        $this->actingAs($seller)->postJson("/api/transactions/{$tx->id}/shipment/documents", [
            'type' => 'bill_of_lading',
            'file' => UploadedFile::fake()->create('bl.pdf', 100),
        ])->assertCreated();

        // 5. Admin: LogisticsPrep → InTransit.
        $this->actingAs($admin)->postJson("/api/admin/transactions/{$tx->id}/shipment/mark-in-transit")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit');

        // 6. Admin upload Import Declaration + Customs Duty Receipt.
        foreach (['import_declaration', 'customs_duty_receipt'] as $type) {
            $this->actingAs($admin)->postJson("/api/transactions/{$tx->id}/shipment/documents", [
                'type' => $type,
                'file' => UploadedFile::fake()->create($type.'.pdf', 100),
            ])->assertCreated();
        }

        // 7. Admin: InTransit → CustomsClearance.
        $this->actingAs($admin)->postJson("/api/admin/transactions/{$tx->id}/shipment/mark-customs-clearance")
            ->assertOk()
            ->assertJsonPath('data.status', 'customs_clearance');

        // 8. Buyer upload Proof of Delivery + Letter of Acceptance.
        foreach (['proof_of_delivery', 'letter_of_acceptance'] as $type) {
            $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment/documents", [
                'type' => $type,
                'file' => UploadedFile::fake()->create($type.'.pdf', 100),
            ])->assertCreated();
        }

        // 9. Admin: CustomsClearance → Delivered.
        $this->actingAs($admin)->postJson("/api/admin/transactions/{$tx->id}/shipment/mark-delivered")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        // actual_arrival otomatis diisi saat delivered.
        $this->assertNotNull($tx->fresh()->shipment->actual_arrival);
    }

    public function test_move_to_logistics_prep_blocked_without_export_declaration(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $tx = $this->transactionOwnedBy($buyer);
        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", $this->initShipmentPayload([
            'cargo_certificate' => UploadedFile::fake()->create('cargo.pdf', 100),
        ]))->assertCreated();

        // Cargo insurance lengkap tapi Export Declaration belum → 422.
        $this->actingAs($admin)->postJson("/api/admin/transactions/{$tx->id}/shipment/mark-logistics-prep")
            ->assertUnprocessable();
    }

    public function test_move_to_logistics_prep_blocked_without_cargo_insurance(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $tx = $this->transactionOwnedBy($buyer, $seller);

        // Init tanpa cargo insurance fields.
        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", [
            'origin_country' => 'ID',
            'destination_country' => 'SG',
            'shipping_mode' => 'sea',
            'carrier_name' => 'X',
        ])->assertCreated();

        $this->actingAs($seller)->postJson("/api/transactions/{$tx->id}/shipment/documents", [
            'type' => 'export_declaration',
            'file' => UploadedFile::fake()->create('e.pdf', 100),
        ])->assertCreated();

        // Export Declaration ada tapi cargo insurance tidak lengkap → 422.
        $this->actingAs($admin)->postJson("/api/admin/transactions/{$tx->id}/shipment/mark-logistics-prep")
            ->assertUnprocessable();
    }

    public function test_regular_admin_manages_transitions_but_seller_cannot(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $tx = $this->transactionOwnedBy($buyer, $seller);
        $this->actingAs($buyer)->postJson("/api/transactions/{$tx->id}/shipment", $this->initShipmentPayload())->assertCreated();

        // Seller coba trigger state transition → route hanya bisa oleh admin.
        $this->actingAs($seller)->postJson("/api/admin/transactions/{$tx->id}/shipment/mark-logistics-prep")
            ->assertForbidden();
    }
}
