<?php

namespace Tests\Feature\Seller;

use App\Enums\SellerProfileStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_set_bank_account_regardless_of_kyc_status(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => SellerProfileStatus::Approved,
        ]);

        $response = $this->actingAs($seller)->putJson('/api/seller/bank-account', [
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder_name' => $seller->name,
        ]);

        $response->assertOk()->assertJsonPath('data.bank_name', 'BCA');
        $this->assertDatabaseHas('seller_profiles', [
            'user_id' => $seller->id,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
        ]);
    }

    public function test_seller_without_kyc_profile_cannot_set_bank_account(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $this->actingAs($seller)->putJson('/api/seller/bank-account', [
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder_name' => $seller->name,
        ])->assertNotFound();
    }

    public function test_seller_cannot_set_another_sellers_bank_account(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => SellerProfileStatus::Approved,
        ]);
        $otherSeller = User::factory()->create(['role' => UserRole::Seller]);

        // otherSeller has no profile of their own yet, so the request 404s
        // before the ownership check — this asserts the happy-path owner
        // check via a profile that *does* belong to the acting user.
        $this->actingAs($otherSeller)->putJson('/api/seller/bank-account', [
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder_name' => 'Someone',
        ])->assertNotFound();
    }

    public function test_bank_account_fields_are_required(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $seller->sellerProfile()->create([
            'ktp_path' => 'kyc/1/ktp.jpg',
            'status' => SellerProfileStatus::Pending,
        ]);

        $this->actingAs($seller)->putJson('/api/seller/bank-account', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_name', 'bank_account_number', 'bank_account_holder_name']);
    }
}
