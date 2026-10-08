<?php

namespace Tests\Feature\Buyer;

use App\Enums\BuyerProfileStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard bahwa buyer wajib KYC + PoF terverifikasi untuk transaksi di atas
 * `config('kyc.high_value_threshold')` (default Rp500jt). Test ini murni fokus
 * pada gate; validasi field checkout lain ada di CheckoutTest.
 */
class HighValueCheckoutGateTest extends TestCase
{
    use RefreshDatabase;

    private function validCheckoutPayload(): array
    {
        return [
            'payment_scheme' => 'full',
            'dp_percent' => 100,
            'insurance_type' => 'none',
            'buyer_address' => 'Jl. Contoh No. 1, Jakarta',
            'buyer_phone' => '08123456789',
        ];
    }

    public function test_high_value_checkout_blocked_without_approved_buyer_kyc(): void
    {
        config(['kyc.high_value_threshold' => 500_000_000]);

        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890001',
            'ktp_name' => 'Buyer HV',
        ]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 800_000_000]);

        $response = $this->actingAs($buyer)->postJson(
            "/api/buyer/vehicles/{$vehicle->id}/checkout",
            $this->validCheckoutPayload()
        );

        $response->assertForbidden();
        $this->assertStringContainsString('KYC & Proof of Funds', $response->json('message'));
    }

    public function test_low_value_checkout_bypasses_buyer_kyc_gate(): void
    {
        config(['kyc.high_value_threshold' => 500_000_000]);

        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890001',
            'ktp_name' => 'Buyer LV',
        ]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $response = $this->actingAs($buyer)->postJson(
            "/api/buyer/vehicles/{$vehicle->id}/checkout",
            $this->validCheckoutPayload()
        );

        $response->assertCreated();
    }

    public function test_high_value_checkout_allowed_after_approved_kyc(): void
    {
        config(['kyc.high_value_threshold' => 500_000_000]);

        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890001',
            'ktp_name' => 'Buyer Approved',
        ]);
        $buyer->buyerProfile()->create([
            'id_type' => 'ktp',
            'id_number' => '3201234567890001',
            'id_document_path' => 'buyer-kyc/1/ktp.jpg',
            'address_proof_path' => 'buyer-kyc/1/addr.pdf',
            'proof_of_funds_path' => 'buyer-kyc/1/pof.pdf',
            'status' => BuyerProfileStatus::Approved,
            'reviewed_at' => now(),
        ]);

        $vehicle = Vehicle::factory()->approved()->create(['price' => 800_000_000]);

        $response = $this->actingAs($buyer)->postJson(
            "/api/buyer/vehicles/{$vehicle->id}/checkout",
            $this->validCheckoutPayload()
        );

        $response->assertCreated();
    }

    public function test_high_value_checkout_blocked_when_kyc_only_pending(): void
    {
        config(['kyc.high_value_threshold' => 500_000_000]);

        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'ktp_number' => '3201234567890001',
            'ktp_name' => 'Buyer Pending',
        ]);
        $buyer->buyerProfile()->create([
            'id_type' => 'ktp',
            'id_number' => '3201234567890001',
            'id_document_path' => 'buyer-kyc/1/ktp.jpg',
            'address_proof_path' => 'buyer-kyc/1/addr.pdf',
            'proof_of_funds_path' => 'buyer-kyc/1/pof.pdf',
            'status' => BuyerProfileStatus::Pending,
        ]);

        $vehicle = Vehicle::factory()->approved()->create(['price' => 800_000_000]);

        $response = $this->actingAs($buyer)->postJson(
            "/api/buyer/vehicles/{$vehicle->id}/checkout",
            $this->validCheckoutPayload()
        );

        $response->assertForbidden();
    }
}
