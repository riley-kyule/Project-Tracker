<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additional to owner_id (the primary lead) and the existing single
        // department_id (the home department) — these let a project bring in
        // more than one person or whole additional departments.
        Schema::create('project_members', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('project_department', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_department');
        Schema::dropIfExists('project_members');
    }
};
