<?php

namespace App\Console\Commands;

use App\Services\RecurrenceService;
use Illuminate\Console\Command;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'ewms:generate-recurring-tasks';

    protected $description = 'Create the next task instance for every due recurrence rule';

    public function handle(): int
    {
        $count = RecurrenceService::generateDueInstances();
        $this->info("Generated {$count} recurring task instance(s).");

        return self::SUCCESS;
    }
}
