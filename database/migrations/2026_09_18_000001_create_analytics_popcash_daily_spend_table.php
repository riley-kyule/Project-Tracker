<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_popcash_daily_spend', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->date('data_date');
            $table->decimal('money_spent', 12, 4)->default(0);
            $table->decimal('cpm', 12, 4)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->timestamps();
            $table->unique(['website_id', 'data_date']);
            $table->index('data_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_popcash_daily_spend');
    }
};
