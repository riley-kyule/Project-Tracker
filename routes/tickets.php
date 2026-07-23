<?php

use App\Http\Controllers\Admin\SlaPolicyController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TrafficDataController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('dashboards/it', [DashboardController::class, 'it'])->name('dashboards.it');
    Route::get('dashboards/ceo', [DashboardController::class, 'ceo'])->name('dashboards.ceo');
    Route::get('dashboards/department', [DashboardController::class, 'department'])->name('dashboards.department');
    Route::get('dashboards/ceo/traffic-data/websites', [TrafficDataController::class, 'websites'])->name('dashboards.ceo.traffic-data.websites');
    Route::get('dashboards/ceo/traffic-data', [TrafficDataController::class, 'index'])->name('dashboards.ceo.traffic-data');
    Route::get('reports/tasks', [ReportController::class, 'tasks'])->name('reports.tasks');
    Route::get('reports/workload', [ReportController::class, 'workload'])->name('reports.workload');
    Route::get('reports/remote-support', [ReportController::class, 'remoteSupport'])->name('reports.remote-support');
    Route::get('search', [SearchController::class, 'index'])->name('search');

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('tickets/{ticket}/transition', [TicketController::class, 'transition'])->name('tickets.transition');
    Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
    Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');
    Route::post('tickets/{ticket}/confirm-resolved', [TicketController::class, 'confirmResolved'])->name('tickets.confirm-resolved');
    Route::post('tickets/{ticket}/convert-to-task', [TicketController::class, 'convertToTask'])->name('tickets.convert');
    Route::post('tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comments.store');
    Route::post('tickets/{ticket}/attachments', [AttachmentController::class, 'storeForTicket'])->name('tickets.attachments.store');
    Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');

    Route::get('admin/sla-policies', [SlaPolicyController::class, 'index'])->name('admin.sla-policies.index');
    Route::patch('admin/sla-policies/{slaPolicy}', [SlaPolicyController::class, 'update'])->name('admin.sla-policies.update');
});
