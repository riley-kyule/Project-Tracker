<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Renames from BoardController::DEFAULT_COLUMNS to their new display
     * labels. Only touches rows still using the original default name and
     * semantic_status pair, so boards that started from
     * DEPARTMENT_COLUMN_PRESETS or were manually renamed are untouched.
     */
    private const RENAMES = [
        ['from' => 'Ideas', 'to' => 'Ideas/Requests', 'semantic_status' => 'idea'],
        ['from' => 'Backlog', 'to' => 'Pending', 'semantic_status' => 'backlog'],
        ['from' => 'Ready', 'to' => 'Recurring', 'semantic_status' => 'ready'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $rename) {
            DB::table('board_columns')
                ->where('name', $rename['from'])
                ->where('semantic_status', $rename['semantic_status'])
                ->update([
                    'name' => $rename['to'],
                    'slug' => Str::slug($rename['to']),
                ]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $rename) {
            DB::table('board_columns')
                ->where('name', $rename['to'])
                ->where('semantic_status', $rename['semantic_status'])
                ->update([
                    'name' => $rename['from'],
                    'slug' => Str::slug($rename['from']),
                ]);
        }
    }
};
