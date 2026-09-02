<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The HR system of record. Deliberately separate from `users`: not every
 * employee has (or keeps) a login, records must survive offboarding, and
 * sensitive PII / statutory identifiers have no business sitting on the
 * authentication table. `user_id` is a nullable link, set when the person
 * also has a platform account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('staff_number')->unique();

            // Personal
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();

            // Kenyan statutory identifiers
            $table->string('national_id_number')->nullable();
            $table->string('kra_pin')->nullable();
            $table->string('nssf_number')->nullable();
            $table->string('shif_number')->nullable();
            $table->string('insurance_membership_number')->nullable();

            // Contact
            $table->string('personal_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alt_phone')->nullable();
            $table->string('postal_address')->nullable();
            $table->string('physical_address')->nullable();
            $table->string('county')->nullable();

            // Employment
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('job_title')->nullable();
            $table->string('employment_type')->default('permanent'); // permanent|contract|casual|intern
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            // Every employee reports to someone and belongs to a department;
            // the one exception is the top of the org chart, flagged here so
            // validation can allow a null manager_id for exactly that person.
            $table->boolean('is_org_head')->default(false);
            $table->date('date_hired')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->string('employment_status')->default('active'); // active|on_probation|on_leave|suspended|terminated
            $table->date('termination_date')->nullable();
            $table->text('termination_reason')->nullable();
            $table->boolean('rehire_eligible')->default(true);

            // Payment details
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('payment_method')->default('bank'); // bank|mpesa|cash|cheque
            $table->string('mpesa_number')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('department_id');
            $table->index('manager_id');
            $table->index('employment_status');
            $table->index('contract_end_date');
        });

        Schema::create('employee_next_of_kin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('employee_id');
        });

        // Tenure history: one row per posting/contract the employee has held.
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('employment_type')->default('permanent');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('reason')->nullable(); // hire|renewal|promotion|transfer|end
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employee_next_of_kin');
        Schema::dropIfExists('employees');
    }
};
