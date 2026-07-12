<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'iso_code' => fake()->unique()->countryCode(),
            'name' => fake()->unique()->country(),
            'is_active' => true,
        ];
    }
}
