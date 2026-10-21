<?php

namespace Database\Factories;

use App\Models\Waste;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Waste> */
class WasteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'amount' => fake()->numberBetween(1, 10),
            'cause' => fake()->randomElement(['Damaged packaging', 'Expired stock', 'Spillage']),
        ];
    }
}
