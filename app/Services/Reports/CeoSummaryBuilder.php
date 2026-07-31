<?php

namespace App\Services\Reports;

use App\Models\Department;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CeoSummaryBuilder
{
    public function __construct(private readonly DepartmentSummaryBuilder $departments) {}

    /** @return array{rows: Collection, totalCompletedToday: int, totalPending: int} */
    public function build(Carbon $businessDay, string $timezone): array
    {
        $rows = Department::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => $this->departments->build($department, $businessDay, $timezone));

        return [
            'rows' => $rows,
            'totalCompletedToday' => (int) $rows->sum('completed_today'),
            'totalPending' => (int) $rows->sum('pending'),
        ];
    }
}
