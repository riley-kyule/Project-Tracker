<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Service Board Requirements Specification v1.0 §8 "Contact
        // quality": the HOD may sample calls or chats for accuracy,
        // professionalism, policy compliance and correct advice. Purely a
        // documented review — it does not feed the automatic achievement
        // calculation (unlike response time / resolution / complaints),
        // since a sample is deliberately not exhaustive; the HOD applies its
        // findings through the ordinary item decision/reason instead.
        Schema::create('cs_contact_quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_interaction_id')->nullable()->constrained('cs_service_interactions')->nullOnDelete();
            $table->string('channel')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('accuracy_ok')->default(true);
            $table->boolean('professionalism_ok')->default(true);
            $table->boolean('policy_compliance_ok')->default(true);
            $table->boolean('correct_advice_ok')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['employee_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_contact_quality_reviews');
    }
};
