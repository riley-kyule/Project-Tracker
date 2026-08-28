<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportDelivery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportDeliveryController extends Controller
{
    public const STATUSES = ['pending', 'sent', 'failed'];

    /** status/sent_at/retry_count are direct columns; Report/Date/Recipient are relation-derived and would need a join to sort by. */
    public const SORTABLE_COLUMNS = ['status', 'sent_at', 'retry_count'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('system.deploy'), 403);

        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $deliveries = ReportDelivery::query()
            ->with([
                'snapshot:id,report_date,report_type,department_id,user_id',
                'snapshot.department:id,name',
                'snapshot.user:id,name',
                'recipient:id,name',
            ])
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when(
                in_array($sort, self::SORTABLE_COLUMNS, true),
                fn ($query) => $query->orderBy($sort, $direction),
                fn ($query) => $query->latest('created_at'),
            )
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/report-deliveries', [
            'deliveries' => $deliveries,
            'statuses' => self::STATUSES,
            'selected' => ['status' => $status],
            'sort' => in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : null,
            'direction' => $direction,
        ]);
    }
}
