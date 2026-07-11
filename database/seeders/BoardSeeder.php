<?php

namespace Database\Seeders;

use App\Http\Controllers\BoardController;
use App\Models\Board;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    /** One department-visible board per department (MVP launch checklist). */
    public function run(): void
    {
        $creator = User::query()->role('Administrator')->first() ?? User::query()->first();

        if ($creator === null) {
            return;
        }

        Department::query()->active()->each(function (Department $department) use ($creator) {
            $board = Board::query()->firstOrCreate(
                ['department_id' => $department->id, 'name' => "{$department->name} Board"],
                ['visibility' => Board::VISIBILITY_DEPARTMENT, 'created_by' => $creator->id],
            );

            if ($board->columns()->doesntExist()) {
                foreach (BoardController::DEFAULT_COLUMNS as $index => $column) {
                    $board->columns()->create([
                        ...$column,
                        'slug' => str($column['name'])->slug(),
                        'position' => $index + 1,
                    ]);
                }
            }
        });
    }
}
