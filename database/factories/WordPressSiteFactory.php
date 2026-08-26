<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WordPressSiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->domainWord().' site',
            'domain' => fake()->unique()->domainName(),
        ];
    }
}
