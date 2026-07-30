<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChecklistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'name' => fake()->words(3, true),
            'position' => 1,
        ];
    }
}
