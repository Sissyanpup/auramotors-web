<?php

namespace Tests\Feature\Buyer;

use App\Enums\BuyerProfileStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyerKycTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_submit_kyc_with_pof(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);

        $response = $this->actingAs($buyer)->postJson('/api/buyer/kyc', [
            'id_type' => 'ktp',
            'id_number' => '3201234567890001',
            'id_document' => UploadedFile::fake()->image('ktp.jpg'),
            'address_proof' => UploadedFile::fake()->create('rekening.pdf', 200),
            'proof_of_funds' => UploadedFile::fake()->create('referensi-bank.pdf', 200),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.id_type', 'ktp');
        $this->assertDatabaseHas('buyer_profiles', [
            'user_id' => $buyer->id,
            'status' => 'pending',
            'id_number' => '3201234567890001',
        ]);
    }

    public function test_seller_cannot_submit_buyer_kyc(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $this->actingAs($seller)->postJson('/api/buyer/kyc', [
            'id_type' => 'ktp',
            'id_number' => '3201234567890001',
            'id_document' => UploadedFile::fake()->image('ktp.jpg'),
            'address_proof' => UploadedFile::fake()->create('rekening.pdf', 200),
            'proof_of_funds' => UploadedFile::fake()->create('referensi-bank.pdf', 200),
        ])->assertForbidden();
    }

    public function test_all_three_docs_are_required(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);

        $this->actingAs($buyer)->postJson('/api/buyer/kyc', [
            'id_type' => 'ktp',
            'id_number' => '320',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'id_document',
            'address_proof',
            'proof_of_funds',
        ]);
    }

    public function test_admin_can_review_buyer_kyc(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $profile = $buyer->buyerProfile()->create([
            'id_type' => 'ktp',
            'id_number' => '3201234567890001',
            'id_document_path' => 'buyer-kyc/1/ktp.jpg',
            'address_proof_path' => 'buyer-kyc/1/addr.pdf',
            'proof_of_funds_path' => 'buyer-kyc/1/pof.pdf',
            'status' => BuyerProfileStatus::Pending,
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/admin/buyer-kyc/{$profile->id}", [
            'status' => 'approved',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('buyer_profiles', [
            'id' => $profile->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertNotNull($profile->fresh()->reviewed_at);
    }

    public function test_admin_reject_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $profile = $buyer->buyerProfile()->create([
            'id_type' => 'ktp',
            'id_number' => '3201234567890001',
            'id_document_path' => 'buyer-kyc/1/ktp.jpg',
            'address_proof_path' => 'buyer-kyc/1/addr.pdf',
            'proof_of_funds_path' => 'buyer-kyc/1/pof.pdf',
            'status' => BuyerProfileStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/buyer-kyc/{$profile->id}", ['status' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_admin_can_list_buyer_kyc_queue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $buyer->buyerProfile()->create([
            'id_type' => 'ktp',
            'id_number' => '3201234567890001',
            'id_document_path' => 'buyer-kyc/1/ktp.jpg',
            'address_proof_path' => 'buyer-kyc/1/addr.pdf',
            'proof_of_funds_path' => 'buyer-kyc/1/pof.pdf',
            'status' => BuyerProfileStatus::Pending,
        ]);

        $this->actingAs($admin)->getJson('/api/admin/buyer-kyc')
            ->assertOk()
            ->assertJsonFragment(['status' => 'pending']);
    }

    public function test_document_stream_owner_only(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $otherBuyer = User::factory()->create(['role' => UserRole::Buyer]);
        $profile = $buyer->buyerProfile()->create([
            'id_type' => 'ktp',
            'id_number' => '3201234567890001',
            'id_document_path' => 'buyer-kyc/1/ktp.jpg',
            'address_proof_path' => 'buyer-kyc/1/addr.pdf',
            'proof_of_funds_path' => 'buyer-kyc/1/pof.pdf',
            'status' => BuyerProfileStatus::Pending,
        ]);

        $this->actingAs($otherBuyer)
            ->getJson("/api/buyer-kyc/{$profile->id}/documents/id_document")
            ->assertForbidden();
    }
}
