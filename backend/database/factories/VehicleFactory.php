<?php

namespace Database\Factories;

use App\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'brand' => fake()->randomElement(['Toyota', 'Honda', 'Suzuki', 'Mitsubishi', 'Daihatsu']),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2010, (int) date('Y')),
            'price' => fake()->numberBetween(80, 800) * 1_000_000,
            'mileage' => fake()->numberBetween(1_000, 150_000),
            'location' => fake()->city(),
            'description' => fake()->paragraph(),
            'specs' => ['transmisi' => fake()->randomElement(['Manual', 'Automatic']), 'warna' => fake()->safeColorName()],
            'status' => VehicleStatus::Draft,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => VehicleStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
