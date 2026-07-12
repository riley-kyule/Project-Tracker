<?php

namespace Database\Factories;

use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'requester_id' => User::factory(),
            'category_id' => fn () => TicketCategory::query()->inRandomOrder()->first()?->id
                ?? TicketCategory::create(['name' => 'General'])->id,
            'priority' => 'medium',
            'impact' => 'medium',
            'urgency' => 'medium',
            'status' => 'new',
        ];
    }
}
