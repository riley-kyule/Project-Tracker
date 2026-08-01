<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            // Which schedule tier triggered this run — also the idempotency
            // guard: RunScheduledBackup checks for a succeeded run of the same
            // frequency within the current period before dispatching another.
            $table->string('frequency');
            $table->string('status')->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('database_file')->nullable();
            $table->string('attachments_file')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['frequency', 'status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
