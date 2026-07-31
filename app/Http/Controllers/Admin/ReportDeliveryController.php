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

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole('Administrator'), 403);

        $status = $request->string('status')->toString();

        $deliveries = ReportDelivery::query()
            ->with([
                'snapshot:id,report_date,report_type,department_id,user_id',
                'snapshot.department:id,name',
                'snapshot.user:id,name',
                'recipient:id,name',
            ])
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/report-deliveries', [
            'deliveries' => $deliveries,
            'statuses' => self::STATUSES,
            'selected' => ['status' => $status],
        ]);
    }
}
