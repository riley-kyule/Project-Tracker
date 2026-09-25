<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per employee per work week — the §2 formula's result,
        // stored rather than recomputed on every dashboard/history/trend
        // query. Recalculated whenever an underlying daily or weekly
        // approval changes. Mirrors seo_final_scores exactly.
        Schema::create('cs_final_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->decimal('avg_daily_approved_score', 6, 2)->nullable();
            $table->unsignedSmallInteger('working_days_counted')->default(0);
            $table->decimal('weekly_approved_score', 6, 2)->nullable();
            $table->decimal('final_score', 6, 2)->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'week_start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_final_scores');
    }
};
