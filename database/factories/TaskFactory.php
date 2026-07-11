<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'board_id' => Board::factory(),
            'board_column_id' => BoardColumn::factory(),
            'position' => 1,
            'created_by' => User::factory(),
            'priority' => 'medium',
        ];
    }
}
