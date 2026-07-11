<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ImportUsers extends Command
{
    protected $signature = 'users:import
        {path : Path to a CSV file with header: name,email,department,job_title,role}
        {--send-reset-links : Email a password setup link to each imported user}
        {--dry-run : Validate the file without creating users}';

    protected $description = 'Import users from a CSV file; unknown roles/departments are reported and skipped';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle);

        if ($header === false || array_diff(['name', 'email'], $header)) {
            fclose($handle);
            $this->error('CSV must contain at least name and email columns.');

            return self::FAILURE;
        }

        $created = 0;
        $skipped = [];
        $row = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $row++;
            $record = array_combine($header, array_pad($values, count($header), null));
            $record = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $record);

            $problem = $this->validateRecord($record);

            if ($problem !== null) {
                $skipped[] = "Row {$row}: {$problem}";

                continue;
            }

            if (! $this->option('dry-run')) {
                $this->createUser($record);
            }

            $created++;
        }

        fclose($handle);

        foreach ($skipped as $line) {
            $this->warn($line);
        }

        $label = $this->option('dry-run') ? 'valid (dry run, none created)' : 'created';
        $this->info("{$created} users {$label}, ".count($skipped).' skipped.');

        return $skipped === [] ? self::SUCCESS : self::FAILURE;
    }

    private function validateRecord(array $record): ?string
    {
        if (empty($record['name']) || empty($record['email'])) {
            return 'missing name or email';
        }

        if (! filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
            return "invalid email {$record['email']}";
        }

        if (User::query()->where('email', $record['email'])->exists()) {
            return "email already exists: {$record['email']}";
        }

        if (! empty($record['department']) && $this->resolveDepartment($record['department']) === null) {
            return "unknown department: {$record['department']}";
        }

        if (! empty($record['role']) && ! Role::query()->where('name', $record['role'])->exists()) {
            return "unknown role: {$record['role']}";
        }

        return null;
    }

    private function createUser(array $record): void
    {
        DB::transaction(function () use ($record) {
            $user = User::create([
                'name' => $record['name'],
                'email' => $record['email'],
                'password' => Str::password(32),
                'department_id' => empty($record['department']) ? null : $this->resolveDepartment($record['department'])?->id,
                'job_title' => $record['job_title'] ?? null,
            ]);

            $user->assignRole(($record['role'] ?? '') ?: 'Employee');
        });

        if ($this->option('send-reset-links')) {
            Password::sendResetLink(['email' => $record['email']]);
        }
    }

    private function resolveDepartment(string $name): ?Department
    {
        return Department::query()
            ->where('slug', Str::slug($name))
            ->orWhere('name', $name)
            ->first();
    }
}
