<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Line items on a seo_daily_cards or seo_weekly_cards row (polymorphic
        // "cardable" — the two card types share identical item-level scoring
        // rules per SEO Board Spec v1.1 §6/§7/§8). Weight/target are snapshotted
        // here at assignment time, independent of seo_task_templates, so a
        // later template edit never rewrites an already-assigned card.
        Schema::create('seo_card_items', function (Blueprint $table) {
            $table->id();
            $table->morphs('cardable');
            $table->foreignId('template_id')->nullable()->constrained('seo_task_templates')->nullOnDelete();
            $table->string('section');
            $table->string('name');
            $table->string('classification')->default('production'); // mandatory | production | scheduled | conditional | additional
            $table->decimal('weight', 6, 2);

            $table->decimal('target_quantity', 10, 2)->nullable();
            $table->decimal('available_target_quantity', 10, 2)->nullable(); // HOD-adjusted when eligible work was short, per §7
            $table->decimal('achieved_quantity', 10, 2)->nullable();
            $table->string('quantity_unit')->nullable();

            $table->text('completion_criteria')->nullable();
            $table->string('evidence_type')->nullable();
            $table->boolean('evidence_required')->default(true);
            $table->time('due_time')->nullable();

            $table->string('employee_status')->default('not_started'); // not_started | in_progress | submitted | blocked
            $table->text('employee_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->string('blocker_reason')->nullable();
            $table->text('blocker_evidence_note')->nullable();
            $table->string('escalated_to')->nullable();
            $table->timestamp('escalated_at')->nullable();

            $table->string('hod_decision')->nullable(); // approved | approved_late | minor_correction | major_rework | rejected | exempted | excluded | carried_forward
            $table->text('hod_decision_reason')->nullable();
            $table->decimal('completion_factor', 5, 2)->nullable(); // 0-100
            $table->decimal('earned_points', 6, 2)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->index(['cardable_type', 'cardable_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_card_items');
    }
};
