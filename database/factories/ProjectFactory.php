<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->catchPhrase(),
            'owner_id' => User::factory(),
            'status' => 'active',
            'health_status' => 'on_track',
            'priority' => 'medium',
        ];
    }
}
