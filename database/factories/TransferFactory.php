<?php

namespace Database\Factories;

use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transfer> */
class TransferFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'amount' => fake()->numberBetween(1, 100),
            'type' => fake()->randomElement(['in', 'out']),
        ];
    }
}
