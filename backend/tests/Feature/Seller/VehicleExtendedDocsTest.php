<?php

namespace Tests\Feature\Seller;

use App\Enums\UserRole;
use App\Enums\VehicleDocumentType;
use App\Enums\VehiclePolicyType;
use App\Enums\VinCheckStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleExtendedDocsTest extends TestCase
{
    use RefreshDatabase;

    private function ownedVehicle(User $seller): Vehicle
    {
        return Vehicle::factory()->create(['seller_id' => $seller->id]);
    }

    public function test_seller_uploads_service_history_document(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/documents", [
                'type' => VehicleDocumentType::ServiceHistory->value,
                'file' => UploadedFile::fake()->create('buku-servis.pdf', 200),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('vehicle_documents', [
            'vehicle_id' => $vehicle->id,
            'type' => 'service_history',
        ]);
    }

    public function test_seller_can_upload_inspection_and_authenticity_docs(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        foreach (['inspection_report', 'certificate_of_authenticity'] as $type) {
            $this->actingAs($seller)
                ->postJson("/api/seller/vehicles/{$vehicle->id}/documents", [
                    'type' => $type,
                    'file' => UploadedFile::fake()->create($type.'.pdf', 200),
                ])
                ->assertCreated();
        }

        $this->assertDatabaseHas('vehicle_documents', ['vehicle_id' => $vehicle->id, 'type' => 'inspection_report']);
        $this->assertDatabaseHas('vehicle_documents', ['vehicle_id' => $vehicle->id, 'type' => 'certificate_of_authenticity']);
    }

    public function test_reupload_same_type_replaces_previous_document(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        $first = $this->actingAs($seller)->postJson("/api/seller/vehicles/{$vehicle->id}/documents", [
            'type' => 'service_history',
            'file' => UploadedFile::fake()->create('v1.pdf', 100),
        ])->json();

        $second = $this->actingAs($seller)->postJson("/api/seller/vehicles/{$vehicle->id}/documents", [
            'type' => 'service_history',
            'file' => UploadedFile::fake()->create('v2.pdf', 100),
        ])->json();

        $this->assertNotEquals($first['id'], $second['id']);
        $this->assertDatabaseMissing('vehicle_documents', ['id' => $first['id']]);
        $this->assertDatabaseHas('vehicle_documents', ['id' => $second['id']]);
    }

    public function test_other_seller_cannot_upload_document(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $intruder = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        $this->actingAs($intruder)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/documents", [
                'type' => 'service_history',
                'file' => UploadedFile::fake()->create('x.pdf', 100),
            ])
            ->assertForbidden();
    }

    public function test_seller_creates_insurance_policy(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/insurance", [
                'policy_type' => VehiclePolicyType::AllRisk->value,
                'insurer_name' => 'PT Asuransi Contoh',
                'policy_number' => 'POL-2026-000123',
                'coverage_amount' => 500_000_000,
                'valid_from' => '2026-01-01',
                'valid_until' => '2026-12-31',
                'certificate' => UploadedFile::fake()->create('polis.pdf', 200),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('vehicle_insurance_policies', [
            'vehicle_id' => $vehicle->id,
            'policy_type' => 'all_risk',
            'insurer_name' => 'PT Asuransi Contoh',
        ]);
    }

    public function test_agreed_value_policy_requires_agreed_value_amount(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/insurance", [
                'policy_type' => 'agreed_value',
                'insurer_name' => 'PT Asuransi Contoh',
                'policy_number' => 'POL-AV-001',
                'coverage_amount' => 2_000_000_000,
                'valid_from' => '2026-01-01',
                'valid_until' => '2027-01-01',
                'certificate' => UploadedFile::fake()->create('polis.pdf', 100),
                // agreed_value_amount sengaja tidak diisi
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agreed_value_amount');
    }

    public function test_valid_until_must_be_after_valid_from(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/insurance", [
                'policy_type' => 'tlo',
                'insurer_name' => 'X',
                'policy_number' => 'A',
                'coverage_amount' => 1,
                'valid_from' => '2026-12-31',
                'valid_until' => '2026-01-01',
                'certificate' => UploadedFile::fake()->create('polis.pdf', 100),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valid_until');
    }

    public function test_vin_check_clean_status(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        $response = $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/vin-checks", [
                'vin' => 'JHMFA36596S000001',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', VinCheckStatus::Clean->value);
        $this->assertDatabaseHas('vehicle_vin_checks', [
            'vehicle_id' => $vehicle->id,
            'status' => 'clean',
        ]);
        $this->assertSame('JHMFA36596S000001', $vehicle->fresh()->vin);
    }

    public function test_vin_check_stolen_flag_returns_blocked_status(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        // VIN test pattern: mengandung "STLN" (dan tetap 17 char, tanpa I/O/Q) → status blocked (deterministic mock).
        $response = $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/vin-checks", [
                'vin' => 'STLN1234567890123',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', VinCheckStatus::Blocked->value);
    }

    public function test_vin_check_accident_flag_returns_warning_status(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        $response = $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/vin-checks", [
                'vin' => 'CRSH1234567890123',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', VinCheckStatus::Warning->value);
    }

    public function test_vin_format_validated(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $vehicle = $this->ownedVehicle($seller);

        // Terlalu pendek + mengandung 'I' (invalid VIN char).
        $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/vin-checks", ['vin' => 'ABC'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vin');

        $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/vin-checks", ['vin' => 'IIIIIIIIIIIIIIIII'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vin');
    }
}
