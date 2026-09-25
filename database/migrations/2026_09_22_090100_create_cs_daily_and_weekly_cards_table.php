<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per employee per workday (Customer Service Board
        // Requirements Specification v1.0 §5). Never deleted or overwritten
        // on rollover — the midnight close (App\Services\Cs\CsCardLifecycleService)
        // creates a new row for the next applicable workday instead of
        // mutating this one. Mirrors seo_daily_cards exactly.
        Schema::create('cs_daily_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('status')->default('open'); // open | closed | reopened
            $table->unsignedSmallInteger('planned_points')->default(100);
            $table->decimal('employee_submitted_points', 6, 2)->nullable();
            $table->decimal('approved_points', 6, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->jsonb('closed_snapshot')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['department_id', 'work_date']);
        });

        // One row per employee per company work week (§6). Targets and
        // platform assignments must be approved before the week begins;
        // items are then locked to in-period adjustments that require a
        // reason, per §9 workflow rule 2 and §3 "target changes after the
        // period begins require a reason and audit record". Mirrors
        // seo_weekly_cards exactly.
        Schema::create('cs_weekly_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->string('status')->default('draft'); // draft | plan_approved | closed | reopened
            $table->unsignedSmallInteger('planned_points')->default(100);
            $table->foreignId('plan_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('plan_approved_at')->nullable();
            $table->decimal('employee_submitted_points', 6, 2)->nullable();
            $table->decimal('approved_points', 6, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->jsonb('closed_snapshot')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'week_start_date']);
            $table->index(['department_id', 'week_start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_weekly_cards');
        Schema::dropIfExists('cs_daily_cards');
    }
};
