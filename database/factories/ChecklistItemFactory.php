<?php

namespace Database\Factories;

use App\Models\Checklist;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'checklist_id' => Checklist::factory(),
            'title' => fake()->sentence(3),
            'position' => 1,
            'is_completed' => false,
        ];
    }
}
