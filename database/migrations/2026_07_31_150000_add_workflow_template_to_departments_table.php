<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Null means "use the generic default columns" — see
            // BoardController::DEPARTMENT_COLUMN_PRESETS.
            $table->string('workflow_template')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('workflow_template');
        });
    }
};
