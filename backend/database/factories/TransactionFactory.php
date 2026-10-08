<?php

namespace Database\Factories;

use App\Enums\EscrowStatus;
use App\Enums\PaymentGatewayDriver;
use App\Enums\TransactionPaymentStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'amount' => fake()->numberBetween(10, 100) * 1_000_000,
            'payment_gateway' => PaymentGatewayDriver::Mock,
            'payment_status' => TransactionPaymentStatus::Pending,
            'gateway_reference' => (string) fake()->uuid(),
            'gateway_invoice_url' => null,
            'paid_at' => null,
            'expires_at' => now()->addDay(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => TransactionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function escrowHold(): static
    {
        return $this->paid()->state(fn () => [
            'escrow_status' => EscrowStatus::EscrowHold,
        ]);
    }

    public function serahTerima(): static
    {
        return $this->escrowHold()->state(fn () => [
            'escrow_status' => EscrowStatus::SerahTerima,
            'buyer_confirmed_at' => now(),
            'seller_confirmed_at' => now(),
        ]);
    }

    public function payoutRelease(): static
    {
        return $this->serahTerima()->state(fn () => [
            'escrow_status' => EscrowStatus::PayoutRelease,
        ]);
    }
}
