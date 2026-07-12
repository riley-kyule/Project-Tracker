<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'trackable_type' => Task::class,
            'trackable_id' => Task::factory(),
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_seconds' => 3600,
            'source' => TimeEntry::SOURCE_TIMER,
            'work_location' => 'remote',
        ];
    }
}
