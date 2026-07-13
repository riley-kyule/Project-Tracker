<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', fn (Blueprint $table) => $table->index('manager_id'));
        Schema::table('boards', function (Blueprint $table) {
            $table->index('created_by');
            $table->index('project_id');
        });
        Schema::table('board_members', fn (Blueprint $table) => $table->index('user_id'));
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('board_column_id');
            $table->index('created_by');
            $table->index('project_id');
            $table->index('recurrence_rule_id');
            $table->index('previous_recurrence_task_id');
            $table->index('approver_id');
            $table->index(['board_id', 'archived_at']);
            $table->index(['primary_assignee_id', 'completed_at', 'archived_at']);
        });
        Schema::table('task_labels', fn (Blueprint $table) => $table->index('label_id'));
        Schema::table('comments', function (Blueprint $table) {
            $table->index('parent_id');
            $table->index('user_id');
        });
        Schema::table('mentions', fn (Blueprint $table) => $table->index('mentioned_user_id'));
        Schema::table('audit_logs', fn (Blueprint $table) => $table->index('actor_id'));
        Schema::table('checklists', fn (Blueprint $table) => $table->index(['task_id', 'position']));
        Schema::table('checklist_items', fn (Blueprint $table) => $table->index('completed_by'));
        Schema::table('attachments', fn (Blueprint $table) => $table->index('uploaded_by'));
        Schema::table('ticket_categories', fn (Blueprint $table) => $table->index('parent_id'));
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('department_id');
            $table->index('assigned_to');
            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('converted_task_id');
            $table->index(['requester_id', 'status']);
        });
        Schema::table('ticket_status_history', function (Blueprint $table) {
            $table->index(['ticket_id', 'created_at']);
            $table->index('changed_by');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->index('department_id');
            $table->index('owner_id');
            $table->index('deadline');
        });
        Schema::table('websites', function (Blueprint $table) {
            $table->index('country_id');
            $table->index('responsible_department_id');
            $table->index('responsible_user_id');
            $table->index('status');
        });
        Schema::table('project_country', fn (Blueprint $table) => $table->index('country_id'));
        Schema::table('project_website', fn (Blueprint $table) => $table->index('website_id'));
        Schema::table('task_country', fn (Blueprint $table) => $table->index('country_id'));
        Schema::table('task_website', fn (Blueprint $table) => $table->index('website_id'));
        Schema::table('task_dependencies', fn (Blueprint $table) => $table->index('overridden_by'));
        Schema::table('recurrence_rules', function (Blueprint $table) {
            $table->index('template_task_id');
            $table->index('created_by');
            $table->index(['is_active', 'next_run_at']);
        });
        Schema::table('time_entries', fn (Blueprint $table) => $table->index('approved_by'));
    }

    public function down(): void
    {
        Schema::table('time_entries', fn (Blueprint $table) => $table->dropIndex(['approved_by']));
        Schema::table('recurrence_rules', function (Blueprint $table) {
            $table->dropIndex(['template_task_id']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['is_active', 'next_run_at']);
        });
        Schema::table('task_dependencies', fn (Blueprint $table) => $table->dropIndex(['overridden_by']));
        Schema::table('task_website', fn (Blueprint $table) => $table->dropIndex(['website_id']));
        Schema::table('task_country', fn (Blueprint $table) => $table->dropIndex(['country_id']));
        Schema::table('project_website', fn (Blueprint $table) => $table->dropIndex(['website_id']));
        Schema::table('project_country', fn (Blueprint $table) => $table->dropIndex(['country_id']));
        Schema::table('websites', function (Blueprint $table) {
            $table->dropIndex(['country_id']);
            $table->dropIndex(['responsible_department_id']);
            $table->dropIndex(['responsible_user_id']);
            $table->dropIndex(['status']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['owner_id']);
            $table->dropIndex(['deadline']);
        });
        Schema::table('ticket_status_history', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'created_at']);
            $table->dropIndex(['changed_by']);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['subcategory_id']);
            $table->dropIndex(['converted_task_id']);
            $table->dropIndex(['requester_id', 'status']);
        });
        Schema::table('ticket_categories', fn (Blueprint $table) => $table->dropIndex(['parent_id']));
        Schema::table('attachments', fn (Blueprint $table) => $table->dropIndex(['uploaded_by']));
        Schema::table('checklist_items', fn (Blueprint $table) => $table->dropIndex(['completed_by']));
        Schema::table('checklists', fn (Blueprint $table) => $table->dropIndex(['task_id', 'position']));
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropIndex(['actor_id']));
        Schema::table('mentions', fn (Blueprint $table) => $table->dropIndex(['mentioned_user_id']));
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['user_id']);
        });
        Schema::table('task_labels', fn (Blueprint $table) => $table->dropIndex(['label_id']));
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['board_column_id']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['project_id']);
            $table->dropIndex(['recurrence_rule_id']);
            $table->dropIndex(['previous_recurrence_task_id']);
            $table->dropIndex(['approver_id']);
            $table->dropIndex(['board_id', 'archived_at']);
            $table->dropIndex(['primary_assignee_id', 'completed_at', 'archived_at']);
        });
        Schema::table('board_members', fn (Blueprint $table) => $table->dropIndex(['user_id']));
        Schema::table('boards', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['project_id']);
        });
        Schema::table('departments', fn (Blueprint $table) => $table->dropIndex(['manager_id']));
    }
};
