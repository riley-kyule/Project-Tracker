<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable named checklist presets, applied to a task to create a real
 * Checklist + ChecklistItems in one action — not a live checklist itself,
 * so items are a flat jsonb array of strings rather than their own table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            // nullOnDelete, not cascade: a shared template outliving the
            // account that created it is the better default — see
            // AGENTS.md/docs/SECURITY.md on preferring deactivation over
            // deletion for exactly this kind of orphaned-reference reason.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->jsonb('items');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_templates');
    }
};
