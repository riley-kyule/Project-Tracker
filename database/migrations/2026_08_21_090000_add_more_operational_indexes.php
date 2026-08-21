<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A few foreign-key columns missed by add_operational_indexes.php — each is
 * currently only the trailing column of a composite unique index, which
 * can't serve a reverse lookup (e.g. "every website this user is assigned
 * to") efficiently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_relations', fn (Blueprint $table) => $table->index('related_task_id'));
        Schema::table('task_assignees', fn (Blueprint $table) => $table->index('user_id'));
        Schema::table('website_assignments', fn (Blueprint $table) => $table->index('user_id'));
        Schema::table('task_confidential_grants', fn (Blueprint $table) => $table->index('granted_by'));
    }

    public function down(): void
    {
        Schema::table('task_confidential_grants', fn (Blueprint $table) => $table->dropIndex(['granted_by']));
        Schema::table('website_assignments', fn (Blueprint $table) => $table->dropIndex(['user_id']));
        Schema::table('task_assignees', fn (Blueprint $table) => $table->dropIndex(['user_id']));
        Schema::table('task_relations', fn (Blueprint $table) => $table->dropIndex(['related_task_id']));
    }
};
