<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "reviewer" collaborator type is retired — task sign-off now lives solely
 * in the task's Approval flow. Existing reviewer rows become plain
 * collaborators so the person keeps their access to the task.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('task_assignees')->where('assignment_type', 'reviewer')->update(['assignment_type' => 'collaborator']);
    }

    public function down(): void
    {
        // Not reversible — "reviewer" vs "collaborator" can't be told apart after the fact.
    }
};
