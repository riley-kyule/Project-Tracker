<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\Seo\SeoHodPanelData;
use App\Services\Seo\SeoPerformanceQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports for the HOD's SEO Board Performance section — SEO Board
 * Requirements Specification v1.1 §11.2 "Exports: Authorised export of the
 * selected employee, department and date range." Department scoping mirrors
 * DashboardController::department exactly, since this exports precisely the
 * data that page already shows; the additional seo.cards.approve check
 * mirrors SeoHodPanelData::forDepartment's own gate on the panel itself.
 */
class SeoExportController extends Controller
{
    public function employee(Request $request, SeoHodPanelData $panelData, SeoPerformanceQuery $query): StreamedResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $seoDepartment = $this->authorizedSeoDepartment($request, $panelData);

        $rows = $query->exportRows($seoDepartment, $validated['employee_id'], Carbon::parse($validated['from']), Carbon::parse($validated['to']));

        return $this->csv($rows, "seo-board-employee-{$validated['employee_id']}-{$validated['from']}-to-{$validated['to']}.csv");
    }

    public function department(Request $request, SeoHodPanelData $panelData, SeoPerformanceQuery $query): StreamedResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $seoDepartment = $this->authorizedSeoDepartment($request, $panelData);

        $rows = $query->exportRows($seoDepartment, null, Carbon::parse($validated['from']), Carbon::parse($validated['to']));

        return $this->csv($rows, "seo-board-{$seoDepartment->name}-{$validated['from']}-to-{$validated['to']}.csv");
    }

    private function authorizedSeoDepartment(Request $request, SeoHodPanelData $panelData): Department
    {
        $user = $request->user();

        $department = $user->hasAnyRole(['CEO', 'Administrator']) && $request->filled('department_id')
            ? Department::query()->findOrFail($request->integer('department_id'))
            : ($user->department ?? abort(404, 'You are not attached to a department.'));

        abort_unless(
            $user->hasAnyRole(['CEO', 'Administrator'])
                || ($user->hasRole('Department Manager') && $user->department_id === $department->id)
                || $department->leads($user->id),
            403,
        );

        abort_unless($user->can('seo.cards.approve') || $user->hasAnyRole(['CEO', 'Administrator']), 403);

        $seoDepartment = $panelData->resolveSeoDepartment($department);
        abort_if($seoDepartment === null, 404);

        return $seoDepartment;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function csv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            if ($rows === []) {
                fputcsv($out, ['No data in the selected range.']);
                fclose($out);

                return;
            }

            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
