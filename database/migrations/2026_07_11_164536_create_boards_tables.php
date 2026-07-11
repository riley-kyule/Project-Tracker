<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('visibility')->default('company')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('department_id');
        });

        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('position');
            $table->string('semantic_status')->default('custom');
            $table->boolean('is_completion_column')->default(false);
            $table->boolean('is_archive_column')->default(false);
            $table->unsignedInteger('wip_limit')->nullable();
            $table->timestamps();

            $table->unique(['board_id', 'slug']);
            $table->index(['board_id', 'position']);
        });

        Schema::create('board_members', function (Blueprint $table) {
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('access_level')->default('view');

            $table->primary(['board_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_members');
        Schema::dropIfExists('board_columns');
        Schema::dropIfExists('boards');
    }
};
