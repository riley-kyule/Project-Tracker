<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Parses the Marketing Statistics module's shared filter bar (website,
 * date range, comparison) from the request, and exposes the exact same
 * shape back via toArray() as the `selected` Inertia prop every page
 * returns — the frontend filter bar reads `selected` and writes the same
 * keys back via router.get(), which is what makes the filters round-trip
 * through the URL query string.
 */
class MarketingStatisticsFilters
{
    public const RANGES = ['last_7_days', 'last_30_days', 'last_90_days', 'custom'];

    public const COMPARISONS = ['none', 'previous_period', 'previous_year', 'custom'];

    private function __construct(
        public readonly string $websiteId,
        public readonly ?string $resolvedWebsiteId,
        public readonly string $range,
        public readonly Carbon $dateFrom,
        public readonly Carbon $dateTo,
        public readonly string $comparison,
        public readonly ?Carbon $compareFrom,
        public readonly ?Carbon $compareTo,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'website_id' => ['nullable', 'string'],
            'range' => ['nullable', 'in:'.implode(',', self::RANGES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'comparison' => ['nullable', 'in:'.implode(',', self::COMPARISONS)],
            'compare_from' => ['nullable', 'date'],
            'compare_to' => ['nullable', 'date', 'after_or_equal:compare_from'],
        ]);

        $websiteId = $validated['website_id'] ?? 'all';
        $range = $validated['range'] ?? 'last_30_days';

        [$dateFrom, $dateTo] = self::resolveRange($range, $validated['date_from'] ?? null, $validated['date_to'] ?? null);

        $comparison = $validated['comparison'] ?? 'none';
        [$compareFrom, $compareTo] = self::resolveComparison(
            $comparison, $dateFrom, $dateTo, $validated['compare_from'] ?? null, $validated['compare_to'] ?? null,
        );

        return new self(
            websiteId: $websiteId,
            resolvedWebsiteId: $websiteId === 'all' ? null : $websiteId,
            range: $range,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            comparison: $comparison,
            compareFrom: $compareFrom,
            compareTo: $compareTo,
        );
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function resolveRange(string $range, ?string $customFrom, ?string $customTo): array
    {
        if ($range === 'custom' && $customFrom && $customTo) {
            return [Carbon::parse($customFrom)->startOfDay(), Carbon::parse($customTo)->startOfDay()];
        }

        $days = match ($range) {
            'last_7_days' => 7,
            'last_90_days' => 90,
            default => 30,
        };

        $to = now()->subDay()->startOfDay();

        return [$to->copy()->subDays($days - 1), $to];
    }

    /** @return array{0: Carbon|null, 1: Carbon|null} */
    private static function resolveComparison(
        string $comparison, Carbon $dateFrom, Carbon $dateTo, ?string $customFrom, ?string $customTo,
    ): array {
        if ($comparison === 'none') {
            return [null, null];
        }

        if ($comparison === 'custom' && $customFrom && $customTo) {
            return [Carbon::parse($customFrom)->startOfDay(), Carbon::parse($customTo)->startOfDay()];
        }

        if ($comparison === 'previous_year') {
            return [$dateFrom->copy()->subYear(), $dateTo->copy()->subYear()];
        }

        // previous_period: the immediately preceding range of equal length.
        $days = $dateFrom->diffInDays($dateTo) + 1;
        $previousTo = $dateFrom->copy()->subDay();

        return [$previousTo->copy()->subDays($days - 1), $previousTo];
    }

    public function hasComparison(): bool
    {
        return $this->compareFrom !== null && $this->compareTo !== null;
    }

    /** Mirrors the query params this request was built from, for URL persistence. */
    public function toArray(): array
    {
        return [
            'website_id' => $this->websiteId,
            'range' => $this->range,
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'comparison' => $this->comparison,
            'compare_from' => $this->compareFrom?->toDateString(),
            'compare_to' => $this->compareTo?->toDateString(),
        ];
    }
}
