<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->foreignId('website_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->unsignedInteger('records_processed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'website_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_sync_logs');
    }
};
