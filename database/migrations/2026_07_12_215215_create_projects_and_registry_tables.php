<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('iso_code', 2)->unique();
            $table->string('name');
            $table->string('region')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('status')->default('planned')->index();
            $table->string('health_status')->default('on_track');
            $table->string('priority')->default('medium');
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform_type')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('responsible_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ga4_property_id')->nullable();
            $table->string('gsc_property')->nullable();
            $table->string('crm_platform_id')->nullable();
            $table->string('ahrefs_target')->nullable();
            $table->string('gtm_container_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('project_country', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'country_id']);
        });

        Schema::create('project_website', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'website_id']);
        });

        Schema::create('task_country', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'country_id']);
        });

        Schema::create('task_website', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'website_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('board_id')->constrained()->nullOnDelete();
        });

        Schema::table('boards', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('task_website');
        Schema::dropIfExists('task_country');
        Schema::dropIfExists('project_website');
        Schema::dropIfExists('project_country');
        Schema::dropIfExists('websites');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('countries');
    }
};
