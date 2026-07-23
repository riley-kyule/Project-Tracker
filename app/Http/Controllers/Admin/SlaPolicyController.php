<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlaPolicy;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SlaPolicyController extends Controller
{
    private const PRIORITY_ORDER = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('tickets.manage'), 403);

        $policies = SlaPolicy::all()
            ->sortBy(fn (SlaPolicy $policy) => self::PRIORITY_ORDER[$policy->priority] ?? 99)
            ->values();

        return Inertia::render('admin/sla-policies/index', [
            'policies' => $policies,
        ]);
    }

    public function update(Request $request, SlaPolicy $slaPolicy): RedirectResponse
    {
        abort_unless($request->user()->can('tickets.manage'), 403);

        $validated = $request->validate([
            'first_response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
            'response_gap_minutes' => ['nullable', 'integer', 'min:1'],
            'business_hours_only' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        $old = $slaPolicy->only(array_keys($validated));
        $slaPolicy->update($validated);
        AuditLogger::log($slaPolicy, 'updated', $old, $slaPolicy->only(array_keys($validated)));

        return back()->with('success', 'SLA policy updated.');
    }
}
