<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll. Every statutory rate lives in `statutory_rate_sets.payload` (an
 * effective-dated JSON blob) so a Kenyan budget change is a data edit, not a
 * code change. Compensation and recurring items are effective-dated too, so a
 * historical payroll run always reconstructs from the values in force then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_rate_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('payload');
            $table->timestamps();

            $table->index('effective_from');
        });

        Schema::create('employee_compensation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('effective_from');
            $table->string('currency', 3)->default('KES');
            $table->decimal('basic_salary', 14, 2);
            $table->jsonb('allowances')->nullable(); // [{name, amount, taxable, pensionable}]
            $table->string('change_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('employee_recurring_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('kind'); // earning|deduction
            $table->string('name');
            $table->string('calc_type')->default('fixed'); // fixed|percent_of_basic
            $table->decimal('amount', 14, 2);
            $table->boolean('is_taxable')->default(true);   // earnings: adds to taxable pay
            $table->boolean('is_pretax')->default(false);   // deductions: taken before PAYE (e.g. pension)
            $table->boolean('affects_nssf')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('balance', 14, 2)->nullable(); // loans / advances — decremented each run
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_active']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('label');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date');
            $table->string('status')->default('draft'); // draft|processing|review|approved|paid|closed
            $table->foreignId('statutory_rate_set_id')->nullable()->constrained('statutory_rate_sets')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('currency', 3)->default('KES');

            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->jsonb('earnings')->nullable();       // [{name, amount, taxable}]
            $table->decimal('gross_pay', 14, 2)->default(0);
            $table->decimal('non_cash_benefits', 14, 2)->default(0);

            $table->jsonb('pretax_deductions')->nullable();
            $table->decimal('taxable_pay', 14, 2)->default(0);
            $table->decimal('paye_before_relief', 14, 2)->default(0);
            $table->decimal('personal_relief', 14, 2)->default(0);
            $table->decimal('insurance_relief', 14, 2)->default(0);
            $table->decimal('paye', 14, 2)->default(0);

            $table->decimal('nssf_employee', 14, 2)->default(0);
            $table->decimal('nssf_employer', 14, 2)->default(0);
            $table->decimal('shif_employee', 14, 2)->default(0);
            $table->decimal('housing_levy_employee', 14, 2)->default(0);
            $table->decimal('housing_levy_employer', 14, 2)->default(0);
            $table->decimal('nita_employer', 14, 2)->default(0);

            $table->jsonb('other_deductions')->nullable(); // [{name, amount}] post-tax
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->decimal('employer_cost', 14, 2)->default(0);

            $table->jsonb('ytd')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_recurring_items');
        Schema::dropIfExists('employee_compensation');
        Schema::dropIfExists('statutory_rate_sets');
    }
};
