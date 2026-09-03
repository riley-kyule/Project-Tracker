<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreStatutoryRateSetRequest;
use App\Models\StatutoryRateSet;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StatutoryRateSetController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', StatutoryRateSet::class);

        return Inertia::render('hr/payroll/rate-sets', [
            'rateSets' => StatutoryRateSet::query()->orderByDesc('effective_from')->get()->map(fn (StatutoryRateSet $rs) => [
                'id' => $rs->id,
                'name' => $rs->name,
                'effective_from' => $rs->effective_from->toDateString(),
                'effective_to' => $rs->effective_to?->toDateString(),
                'is_active' => $rs->is_active,
                'payload' => $rs->payload,
            ]),
        ]);
    }

    public function store(StoreStatutoryRateSetRequest $request): RedirectResponse
    {
        Gate::authorize('create', StatutoryRateSet::class);

        $rateSet = StatutoryRateSet::create([
            ...$request->safe()->except('payload'),
            'payload' => $request->validated('payload'),
            'created_by' => $request->user()->id,
        ]);
        AuditLogger::log($rateSet, 'created', [], ['name' => $rateSet->name]);

        return back()->with('success', 'Rate set added.');
    }

    public function update(StoreStatutoryRateSetRequest $request, StatutoryRateSet $rateSet): RedirectResponse
    {
        Gate::authorize('update', $rateSet);

        $old = $rateSet->only(['name', 'effective_from', 'effective_to', 'is_active', 'payload']);
        $rateSet->update([
            ...$request->safe()->except('payload'),
            'payload' => $request->validated('payload'),
        ]);
        AuditLogger::log($rateSet, 'updated', $old, $rateSet->only(['name', 'effective_from', 'effective_to', 'is_active', 'payload']));

        return back()->with('success', 'Rate set updated.');
    }
}
