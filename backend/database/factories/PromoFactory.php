<?php

namespace Database\Factories;

use App\Models\Promo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promo>
 */
class PromoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Promo '.fake()->word(),
            'kind' => Promo::KIND_AUTO,
            'benefit' => Promo::BENEFIT_DISCOUNT,
            'discount_type' => 'percent',
            'discount_value' => 2,
            'max_discount' => 10_000_000,
            'badge' => 'Promo',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'is_active' => true,
        ];
    }
}
