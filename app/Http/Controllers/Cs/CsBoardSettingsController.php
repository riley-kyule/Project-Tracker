<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Services\AuditLogger;
use App\Services\Cs\CsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Company-wide Customer Service Board settings that the requirements
 * specification says must be changeable without a code deployment: the
 * per-channel response-time standard (§8), the reporting currency (§4), and
 * the calibration end date (§14). Gated the same way as notification
 * settings — cs.settings.manage plus leading the department — so this stays
 * with the people actually running the board, not general company admin.
 */
class CsBoardSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('cs.settings.manage') && CsAccess::leadsBoard($request->user()), 403);

        $validated = $request->validate([
            'cs_reporting_currency' => ['required', 'string', 'size:3'],
            'cs_response_time_standards' => ['required', 'array'],
            'cs_response_time_standards.chat' => ['required', 'integer', 'min:1', 'max:1440'],
            'cs_response_time_standards.call' => ['required', 'integer', 'min:1', 'max:1440'],
            'cs_response_time_standards.email' => ['required', 'integer', 'min:1', 'max:1440'],
            'cs_response_time_standards.other' => ['required', 'integer', 'min:1', 'max:1440'],
            'cs_calibration_ends_at' => ['nullable', 'date'],
        ]);

        $validated['cs_reporting_currency'] = strtoupper($validated['cs_reporting_currency']);

        $setting = CompanySetting::current();
        $old = $setting->only(array_keys($validated));
        $setting->update($validated);

        AuditLogger::log($setting, 'cs_board_settings.updated', $old, $setting->only(array_keys($validated)));

        return back()->with('success', 'Customer Service Board settings updated.');
    }
}
