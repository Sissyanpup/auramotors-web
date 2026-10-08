<?php

namespace Tests\Feature\Seller;

use App\Enums\SellerProfileStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerKycTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_submit_kyc(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($seller)->postJson('/api/seller/kyc', [
            'entity_type' => 'individu',
            'ktp' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.entity_type', 'individu');
        $this->assertDatabaseHas('seller_profiles', [
            'user_id' => $seller->id,
            'status' => 'pending',
            'entity_type' => 'individu',
        ]);
    }

    public function test_seller_perusahaan_requires_entity_documents(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        // Tanpa dokumen entity → validation gagal untuk perusahaan.
        $this->actingAs($seller)->postJson('/api/seller/kyc', [
            'entity_type' => 'perusahaan',
            'ktp' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'company_registration',
            'articles_of_association',
            'ubo_declaration',
        ]);
    }

    public function test_seller_perusahaan_submits_full_entity_kyc(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($seller)->postJson('/api/seller/kyc', [
            'entity_type' => 'perusahaan',
            'ktp' => UploadedFile::fake()->image('ktp.jpg'),
            'company_registration' => UploadedFile::fake()->create('nib.pdf', 100),
            'articles_of_association' => UploadedFile::fake()->create('akta.pdf', 100),
            'ubo_declaration' => UploadedFile::fake()->create('ubo.pdf', 100),
        ]);

        $response->assertCreated()->assertJsonPath('data.entity_type', 'perusahaan');
        $this->assertDatabaseHas('seller_profiles', [
            'user_id' => $seller->id,
            'entity_type' => 'perusahaan',
        ]);
    }

    public function test_buyer_cannot_submit_kyc(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);

        $this->actingAs($buyer)->postJson('/api/seller/kyc', [
            'entity_type' => 'individu',
            'ktp' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertForbidden();
    }

    public function test_admin_can_list_pending_kyc_queue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => SellerProfileStatus::Pending,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/kyc');

        $response->assertOk()->assertJsonFragment(['status' => 'pending']);
    }

    public function test_admin_can_approve_kyc_with_audit_trail(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => SellerProfileStatus::Pending,
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/admin/kyc/{$profile->id}", [
            'status' => 'approved',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('seller_profiles', [
            'id' => $profile->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertNotNull($profile->fresh()->reviewed_at);
    }

    public function test_reject_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => SellerProfileStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/kyc/{$profile->id}", ['status' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_seller_cannot_review_own_kyc(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => SellerProfileStatus::Pending,
        ]);

        $this->actingAs($seller)
            ->patchJson("/api/admin/kyc/{$profile->id}", ['status' => 'approved'])
            ->assertForbidden();
    }
}
