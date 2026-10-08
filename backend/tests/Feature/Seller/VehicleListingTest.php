<?php

namespace Tests\Feature\Seller;

use App\Enums\SellerProfileStatus;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleListingTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/x/ktp.jpg',
            'status' => SellerProfileStatus::Approved,
        ]);

        return $seller;
    }

    private function vehiclePayload(): array
    {
        return [
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'year' => 2020,
            'price' => 150000000,
            'mileage' => 30000,
            'location' => 'Tangerang',
            'photos' => [UploadedFile::fake()->image('photo.jpg')],
        ];
    }

    public function test_seller_without_approved_kyc_cannot_create_listing(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $this->actingAs($seller)
            ->postJson('/api/seller/vehicles', $this->vehiclePayload())
            ->assertForbidden();
    }

    public function test_approved_seller_can_create_draft_listing(): void
    {
        Storage::fake('public');
        $seller = $this->approvedSeller();

        $response = $this->actingAs($seller)->postJson('/api/seller/vehicles', $this->vehiclePayload());

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('vehicles', ['seller_id' => $seller->id, 'brand' => 'Toyota', 'status' => 'draft']);
    }

    public function test_submit_for_review_requires_documents(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $seller = $this->approvedSeller();
        $vehicle = $seller->vehicles()->create([...$this->vehiclePayloadWithoutFiles(), 'status' => VehicleStatus::Draft]);
        $vehicle->photos()->create(['path' => 'vehicles/1/a.jpg']);

        $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}/submit-for-review")
            ->assertUnprocessable();
    }

    public function test_listing_not_visible_in_public_catalog_until_approved(): void
    {
        Storage::fake('public');
        $seller = $this->approvedSeller();
        $vehicle = $seller->vehicles()->create([...$this->vehiclePayloadWithoutFiles(), 'status' => VehicleStatus::PendingReview]);
        $vehicle->photos()->create(['path' => 'vehicles/1/a.jpg']);

        $this->getJson('/api/vehicles')->assertOk()->assertJsonMissing(['id' => $vehicle->id]);
        $this->getJson("/api/vehicles/{$vehicle->id}")->assertNotFound();
    }

    public function test_approved_listing_is_visible_in_public_catalog(): void
    {
        Storage::fake('public');
        $seller = $this->approvedSeller();
        $vehicle = $seller->vehicles()->create([...$this->vehiclePayloadWithoutFiles(), 'status' => VehicleStatus::Approved]);
        $vehicle->photos()->create(['path' => 'vehicles/1/a.jpg']);

        $this->getJson('/api/vehicles')->assertOk()->assertJsonFragment(['id' => $vehicle->id]);
    }

    public function test_seller_cannot_edit_listing_after_it_is_approved(): void
    {
        Storage::fake('public');
        $seller = $this->approvedSeller();
        $vehicle = $seller->vehicles()->create([...$this->vehiclePayloadWithoutFiles(), 'status' => VehicleStatus::Approved]);

        $this->actingAs($seller)
            ->postJson("/api/seller/vehicles/{$vehicle->id}", ['brand' => 'Honda'])
            ->assertForbidden();
    }

    public function test_admin_can_list_pending_review_queue(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = $this->approvedSeller();
        $seller->vehicles()->create([...$this->vehiclePayloadWithoutFiles(), 'status' => VehicleStatus::PendingReview]);

        $response = $this->actingAs($admin)->getJson('/api/admin/vehicles');

        $response->assertOk()->assertJsonFragment(['status' => 'pending_review']);
    }

    public function test_admin_can_view_full_listing_detail_with_documents(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = $this->approvedSeller();
        $vehicle = $seller->vehicles()->create([...$this->vehiclePayloadWithoutFiles(), 'status' => VehicleStatus::PendingReview]);
        $vehicle->documents()->create(['type' => 'stnk', 'path' => 'vehicles/1/documents/stnk.jpg']);

        $response = $this->actingAs($admin)->getJson("/api/admin/vehicles/{$vehicle->id}");

        $response->assertOk()->assertJsonPath('data.documents.0.type', 'stnk');
    }

    public function test_seller_cannot_use_admin_route_to_view_listing_detail(): void
    {
        $otherSeller = User::factory()->create(['role' => UserRole::Seller]);
        $seller = $this->approvedSeller();
        $vehicle = $seller->vehicles()->create([...$this->vehiclePayloadWithoutFiles(), 'status' => VehicleStatus::PendingReview]);

        $this->actingAs($otherSeller)
            ->getJson("/api/admin/vehicles/{$vehicle->id}")
            ->assertForbidden();
    }

    public function test_admin_approve_sets_audit_trail(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = $this->approvedSeller();
        $vehicle = $seller->vehicles()->create([...$this->vehiclePayloadWithoutFiles(), 'status' => VehicleStatus::PendingReview]);

        $response = $this->actingAs($admin)->patchJson("/api/admin/vehicles/{$vehicle->id}", [
            'status' => 'approved',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    private function vehiclePayloadWithoutFiles(): array
    {
        return [
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'year' => 2020,
            'price' => 150000000,
            'mileage' => 30000,
            'location' => 'Tangerang',
        ];
    }
}
