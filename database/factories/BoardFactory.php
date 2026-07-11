<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Board',
            'department_id' => Department::factory(),
            'visibility' => 'company',
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
