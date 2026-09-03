<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payslip letterhead, payslip email dispatch timing, and whether a payroll
 * run needs a second (CEO/Admin) sign-off before the HR Manager can send
 * payslips out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('payslip_company_name')->nullable()->after('payslip_footer_note');
            $table->text('payslip_company_address')->nullable()->after('payslip_company_name');
            $table->string('payslip_logo_path')->nullable()->after('payslip_company_address');
            $table->string('payslip_dispatch_timing')->default('on_mark_paid')->after('payslip_logo_path'); // on_mark_paid|on_pay_date
            $table->boolean('payroll_requires_second_approval')->default(false)->after('payslip_dispatch_timing');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'payslip_company_name', 'payslip_company_address', 'payslip_logo_path',
                'payslip_dispatch_timing', 'payroll_requires_second_approval',
            ]);
        });
    }
};
