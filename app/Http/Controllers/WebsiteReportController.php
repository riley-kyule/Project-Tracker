<?php

namespace App\Http\Controllers;

use App\Services\Reports\WebsiteReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Reports" — a team member ticks a subset of the websites they've been
 * assigned (see WebsiteAssignment) and generates a report scoped to
 * exactly those sites, for a chosen date range. No permission gate beyond
 * `auth`: assignment itself is the authorization boundary (see
 * WebsiteReportBuilder, which only ever queries the requesting user's own
 * assignments — a user can never pull data for a site they aren't on).
 */
class WebsiteReportController extends Controller
{
    public function index(Request $request, WebsiteReportBuilder $builder): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'website_ids' => ['nullable', 'array'],
            'website_ids.*' => ['integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        [$dateFrom, $dateTo] = $this->resolveDates($validated);
        $selectedIds = array_map('intval', $validated['website_ids'] ?? []);

        return Inertia::render('reports/my-reports', [
            'assigned_websites' => $this->assignedWebsites($user),
            'selected' => [
                'website_ids' => $selectedIds,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'report' => $selectedIds === [] ? null : $builder->build($user, $selectedIds, $dateFrom, $dateTo),
        ]);
    }

    public function export(Request $request, WebsiteReportBuilder $builder): HttpResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'website_ids' => ['required', 'array', 'min:1'],
            'website_ids.*' => ['integer'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->startOfDay();
        $selectedIds = array_map('intval', $validated['website_ids']);

        $report = $builder->build($user, $selectedIds, $dateFrom, $dateTo);

        $pdf = Pdf::loadView('reports.website-report-pdf', [
            'report' => $report,
            'user' => $user,
            'generatedAt' => now(),
        ]);

        return $pdf->download("website-report-{$dateFrom->toDateString()}-to-{$dateTo->toDateString()}.pdf");
    }

    /** @return array<int, array{id: int, name: string, domain: string|null, team: string}> */
    private function assignedWebsites($user): array
    {
        return $user->assignedWebsites()
            ->orderBy('name')
            ->get(['websites.id', 'websites.name', 'websites.domain'])
            ->map(fn ($website) => [
                'id' => $website->id,
                'name' => $website->name,
                'domain' => $website->domain,
                'team' => $website->pivot->team,
            ])
            ->all();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolveDates(array $validated): array
    {
        $dateTo = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->startOfDay() : now()->subDay()->startOfDay();
        $dateFrom = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : $dateTo->copy()->subDays(29);

        return [$dateFrom, $dateTo];
    }
}
