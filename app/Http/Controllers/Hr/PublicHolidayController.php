<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePublicHolidayRequest;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PublicHolidayController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        return Inertia::render('hr/leave/holidays', [
            'holidays' => PublicHoliday::query()->orderBy('date')->get()->map(fn (PublicHoliday $h) => [
                'id' => $h->id,
                'name' => $h->name,
                'date' => $h->date->toDateString(),
                'is_recurring' => $h->is_recurring,
            ]),
        ]);
    }

    public function store(StorePublicHolidayRequest $request): RedirectResponse
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        PublicHoliday::create($request->validated());

        return back()->with('success', 'Holiday added.');
    }

    public function update(StorePublicHolidayRequest $request, PublicHoliday $holiday): RedirectResponse
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        $holiday->update($request->validated());

        return back()->with('success', 'Holiday updated.');
    }

    public function destroy(PublicHoliday $holiday): RedirectResponse
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}
