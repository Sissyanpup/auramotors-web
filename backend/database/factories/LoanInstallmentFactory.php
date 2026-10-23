<?php

namespace Database\Factories;

use App\Models\LoanInstallment;
use App\Models\VehicleLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanInstallment>
 */
class LoanInstallmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_loan_id' => VehicleLoan::factory(),
            'sequence' => 1,
            'due_date' => now()->addMonthNoOverflow()->toDateString(),
            'principal_amount' => 6_666_667,
            'interest_amount' => 533_333,
            'amount' => 7_200_000,
        ];
    }
}
