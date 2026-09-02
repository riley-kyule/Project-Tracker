<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePerformanceCycleRequest;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\AuditLogger;
use App\Services\Hr\PerformanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceCycleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PerformanceReview::class);

        $canManage = $request->user()->can('hr.performance.manage');
        $selected = $request->integer('cycle') ?: PerformanceCycle::query()->latest('period_start')->value('id');

        $reviews = $selected
            ? PerformanceReview::query()
                ->where('performance_cycle_id', $selected)
                ->with('employee:id,first_name,middle_name,last_name,department_id', 'reviewer:id,name')
                ->get()
                ->filter(fn (PerformanceReview $r) => $request->user()->can('view', $r))
                ->values()
                ->map(fn (PerformanceReview $r) => [
                    'id' => $r->id,
                    'employee' => $r->employee->full_name,
                    'reviewer' => $r->reviewer?->name,
                    'status' => $r->status,
                    'overall_rating' => $r->overall_rating !== null ? (float) $r->overall_rating : null,
                ])
            : [];

        return Inertia::render('hr/performance/index', [
            'cycles' => PerformanceCycle::query()
                ->withCount('reviews')
                ->orderByDesc('period_start')
                ->get()
                ->map(fn (PerformanceCycle $c) => [
                    ...$c->only(['id', 'name', 'type', 'status']),
                    'period_start' => $c->period_start->toDateString(),
                    'period_end' => $c->period_end->toDateString(),
                    'reviews_count' => $c->reviews_count,
                ]),
            'selectedCycleId' => $selected,
            'reviews' => $reviews,
            'canManage' => $canManage,
        ]);
    }

    public function store(StorePerformanceCycleRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.performance.manage'), 403);

        $cycle = PerformanceCycle::create([...$request->validated(), 'created_by' => $request->user()->id]);
        AuditLogger::log($cycle, 'created', [], ['name' => $cycle->name]);

        return redirect()->route('hr.performance.index', ['cycle' => $cycle->id])->with('success', 'Cycle created.');
    }

    public function update(StorePerformanceCycleRequest $request, PerformanceCycle $cycle): RedirectResponse
    {
        abort_unless($request->user()->can('hr.performance.manage'), 403);

        $cycle->update($request->validated());

        return back()->with('success', 'Cycle updated.');
    }

    public function activate(Request $request, PerformanceCycle $cycle, PerformanceService $service): RedirectResponse
    {
        abort_unless($request->user()->can('hr.performance.manage'), 403);

        $created = $service->activate($cycle);

        return back()->with('success', "Cycle activated — {$created} review(s) opened.");
    }
}
