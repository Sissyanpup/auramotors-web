<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Enums\PaymentScheme;
use App\Models\Transaction;
use App\Models\VehicleLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleLoan>
 */
class VehicleLoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory()->escrowHold()->state(['payment_scheme' => PaymentScheme::Credit]),
            'lender_name' => 'PT Aura Finance Indonesia (Simulasi)',
            'contract_number' => 'PK/AURA/'.fake()->unique()->numerify('######'),
            'status' => LoanStatus::Submitted,
            'vehicle_financed' => 80_000_000,
            'insurance_financed' => 0,
            'principal' => 80_000_000,
            'flat_rate' => 0.08,
            'tenor_months' => 12,
            'interest_total' => 6_400_000,
            'monthly_installment' => 7_200_000,
            'total_payable' => 86_400_000,
            'effective_rate' => 0.1449,
            'submitted_at' => now(),
            'auto_approve_at' => now()->addMinute(),
        ];
    }
}
