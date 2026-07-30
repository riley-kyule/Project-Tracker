<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'commentable_type' => Task::class,
            'commentable_id' => Task::factory(),
            'parent_id' => null,
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'is_internal' => false,
        ];
    }
}
