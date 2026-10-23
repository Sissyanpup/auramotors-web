<?php

namespace Database\Factories;

use App\Models\SellerBonus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerBonus>
 */
class SellerBonusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => User::factory()->state(['role' => 'seller']),
            'type' => SellerBonus::TYPE_SALE,
            'amount' => 1_500_000,
            'basis' => '0,5% × harga kendaraan',
            'status' => 'paid',
            'paid_at' => now(),
        ];
    }
}
