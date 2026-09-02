<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('company_kra_pin')->nullable()->after('hr_from_name');
            $table->string('nssf_employer_number')->nullable()->after('company_kra_pin');
            $table->string('shif_employer_number')->nullable()->after('nssf_employer_number');
            $table->string('payroll_currency', 3)->default('KES')->after('shif_employer_number');
            $table->unsignedTinyInteger('default_pay_day')->default(28)->after('payroll_currency');
            $table->string('payslip_footer_note')->nullable()->after('default_pay_day');
            $table->boolean('nita_levy_enabled')->default(true)->after('payslip_footer_note');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_kra_pin', 'nssf_employer_number', 'shif_employer_number',
                'payroll_currency', 'default_pay_day', 'payslip_footer_note', 'nita_levy_enabled',
            ]);
        });
    }
};
