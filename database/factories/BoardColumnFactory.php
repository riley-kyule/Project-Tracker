<?php

namespace Database\Factories;

use App\Models\Board;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoardColumnFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'board_id' => Board::factory(),
            'name' => $name,
            'slug' => str($name)->slug(),
            'position' => 1,
            'semantic_status' => 'custom',
        ];
    }
}
