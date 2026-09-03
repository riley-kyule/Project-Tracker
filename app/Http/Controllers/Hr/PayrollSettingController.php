<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\UpdatePayrollSettingRequest;
use App\Models\CompanySetting;
use App\Models\PayrollPeriod;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One place for payroll configuration: employer identifiers, the payslip
 * letterhead (logo + company name/address/footer), when payslip emails go
 * out, and whether a run needs a second sign-off before dispatch.
 */
class PayrollSettingController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('create', PayrollPeriod::class); // hr.payroll.process

        $s = CompanySetting::current();

        return Inertia::render('hr/payroll/settings', [
            'settings' => [
                'company_kra_pin' => $s->company_kra_pin,
                'nssf_employer_number' => $s->nssf_employer_number,
                'shif_employer_number' => $s->shif_employer_number,
                'payroll_currency' => $s->payroll_currency ?: 'KES',
                'default_pay_day' => (int) ($s->default_pay_day ?: 28),
                'nita_levy_enabled' => (bool) $s->nita_levy_enabled,
                'payslip_company_name' => $s->payslip_company_name,
                'payslip_company_address' => $s->payslip_company_address,
                'payslip_footer_note' => $s->payslip_footer_note,
                'payslip_dispatch_timing' => $s->payslip_dispatch_timing ?: 'on_mark_paid',
                'payroll_requires_second_approval' => (bool) $s->payroll_requires_second_approval,
                'has_logo' => filled($s->payslip_logo_path) && Storage::disk('local')->exists($s->payslip_logo_path),
            ],
        ]);
    }

    public function update(UpdatePayrollSettingRequest $request): RedirectResponse
    {
        Gate::authorize('create', PayrollPeriod::class);

        $settings = CompanySetting::current();
        $old = $settings->only(array_keys($request->validated()));
        $settings->update($request->validated());
        AuditLogger::log($settings, 'payroll_settings_updated', $old, $settings->only(array_keys($request->validated())));

        return back()->with('success', 'Payroll settings updated.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        Gate::authorize('create', PayrollPeriod::class);

        $request->validate(['logo' => ['required', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max('2mb')]]);

        $settings = CompanySetting::current();
        if ($settings->payslip_logo_path) {
            Storage::disk('local')->delete($settings->payslip_logo_path);
        }

        $path = $request->file('logo')->store('hr/payroll', 'local');
        $settings->update(['payslip_logo_path' => $path]);

        return back()->with('success', 'Logo uploaded.');
    }

    public function deleteLogo(): RedirectResponse
    {
        Gate::authorize('create', PayrollPeriod::class);

        $settings = CompanySetting::current();
        if ($settings->payslip_logo_path) {
            Storage::disk('local')->delete($settings->payslip_logo_path);
            $settings->update(['payslip_logo_path' => null]);
        }

        return back()->with('success', 'Logo removed.');
    }

    public function logo(): StreamedResponse
    {
        Gate::authorize('viewAny', PayrollPeriod::class);

        $path = CompanySetting::current()->payslip_logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
