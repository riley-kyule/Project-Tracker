<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Which page this filter applies to (e.g. 'reports.tasks') — lets
            // the same table serve more than one filterable screen later
            // without a schema change.
            $table->string('scope');
            $table->string('name');
            $table->jsonb('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'scope', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
    }
};
