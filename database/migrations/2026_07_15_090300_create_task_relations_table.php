<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Symmetric task-to-task link ("related to"), unlike the directional
     * task_dependencies table. Always stored with the lower task ID as
     * task_id so a pair can never be recorded twice in opposite directions.
     */
    public function up(): void
    {
        Schema::create('task_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('related_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('relation_type')->default('related_to');
            $table->timestamps();

            $table->unique(['task_id', 'related_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_relations');
    }
};
