<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-off: create an {@see Employee} record for every active user who doesn't
 * have one yet, linked back to the account. Names are split on the first
 * space; HR fills in the rest afterwards. Safe to re-run — it skips users
 * already linked.
 */
class BackfillEmployees extends Command
{
    protected $signature = 'ewms:hr-backfill-employees {--dry-run}';

    protected $description = 'Create linked employee records for active users that lack one';

    public function handle(): int
    {
        $users = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereDoesntHave('employee')
            ->with('department:id,name')
            ->orderBy('name')
            ->get();

        if ($users->isEmpty()) {
            $this->info('Every active user already has an employee record.');

            return self::SUCCESS;
        }

        $seq = (int) (Employee::query()->max('id') ?? 0);
        $created = 0;

        foreach ($users as $user) {
            [$first, $last] = $this->splitName($user->name);
            $staffNumber = 'EMP-'.str_pad((string) (++$seq), 4, '0', STR_PAD_LEFT);

            $this->line("  {$user->name} → {$staffNumber}".($user->department ? " ({$user->department->name})" : ''));

            if ($this->option('dry-run')) {
                continue;
            }

            Employee::create([
                'user_id' => $user->id,
                'staff_number' => $staffNumber,
                'first_name' => $first,
                'last_name' => $last,
                'personal_email' => $user->email,
                'department_id' => $user->department_id,
                'job_title' => $user->job_title,
                'employment_type' => 'permanent',
                'employment_status' => Employee::STATUS_ACTIVE,
                'payment_method' => 'bank',
                'is_org_head' => $user->manager_id === null,
                'date_hired' => $user->created_at?->toDateString(),
                'contract_start_date' => $user->created_at?->toDateString(),
            ]);
            $created++;
        }

        // Second pass: wire up manager_id from the users' manager chain.
        if (! $this->option('dry-run')) {
            Employee::query()->whereNotNull('user_id')->with('user')->each(function (Employee $employee) {
                $managerUserId = $employee->user?->manager_id;
                if ($managerUserId) {
                    $managerEmployeeId = Employee::query()->where('user_id', $managerUserId)->value('id');
                    if ($managerEmployeeId && $employee->manager_id !== $managerEmployeeId) {
                        $employee->update(['manager_id' => $managerEmployeeId, 'is_org_head' => false]);
                    }
                }
            });
        }

        $this->info($this->option('dry-run') ? "{$users->count()} would be created." : "Created {$created} employee record(s).");

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [$name];

        return [$parts[0] ?? $name, $parts[1] ?? Str::of($name)->afterLast(' ')->toString() ?: '—'];
    }
}
