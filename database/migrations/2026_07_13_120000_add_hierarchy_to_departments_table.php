<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('parent_department_id')->nullable()->after('id')->constrained('departments')->nullOnDelete();
            $table->foreignId('assistant_manager_id')->nullable()->after('manager_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_department_id');
            $table->dropConstrainedForeignId('assistant_manager_id');
        });
    }
};
