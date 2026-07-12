<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurrenceRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'template_task_id' => Task::factory(),
            'frequency' => 'weekly',
            'interval_value' => 1,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
