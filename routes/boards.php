<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RecurrenceRuleController;
use App\Http\Controllers\TaskApprovalController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskDependencyController;
use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
    Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
    Route::get('boards/{board}', [BoardController::class, 'show'])->name('boards.show');
    Route::patch('boards/{board}', [BoardController::class, 'update'])->name('boards.update');

    Route::post('boards/{board}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::get('tasks/{task}/activity', [TaskController::class, 'activity'])->name('tasks.activity');
    Route::get('tasks/{task}/detail', [TaskController::class, 'detail'])->name('tasks.detail');
    Route::post('tasks/{task}/dependencies', [TaskDependencyController::class, 'store'])->name('task-dependencies.store');
    Route::delete('task-dependencies/{dependency}', [TaskDependencyController::class, 'destroy'])->name('task-dependencies.destroy');
    Route::post('tasks/{task}/recurrence', [RecurrenceRuleController::class, 'store'])->name('recurrence-rules.store');
    Route::patch('recurrence-rules/{rule}', [RecurrenceRuleController::class, 'update'])->name('recurrence-rules.update');

    Route::post('tasks/{task}/time-entries/start', [TimeEntryController::class, 'start'])->name('time-entries.start');
    Route::post('time-entries/{entry}/stop', [TimeEntryController::class, 'stop'])->name('time-entries.stop');
    Route::post('tasks/{task}/time-entries', [TimeEntryController::class, 'storeManual'])->name('time-entries.store');
    Route::post('time-entries/{entry}/approve', [TimeEntryController::class, 'approve'])->name('time-entries.approve');
    Route::post('time-entries/{entry}/reject', [TimeEntryController::class, 'reject'])->name('time-entries.reject');

    Route::post('tasks/{task}/request-approval', [TaskApprovalController::class, 'request'])->name('task-approvals.request');
    Route::post('tasks/{task}/approve-review', [TaskApprovalController::class, 'approve'])->name('task-approvals.approve');
    Route::post('tasks/{task}/reject-review', [TaskApprovalController::class, 'reject'])->name('task-approvals.reject');

    Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('tasks/{task}/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
    Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');
    Route::post('checklists/{checklist}/items', [ChecklistController::class, 'storeItem'])->name('checklist-items.store');
    Route::patch('checklist-items/{item}', [ChecklistController::class, 'updateItem'])->name('checklist-items.update');
    Route::delete('checklist-items/{item}', [ChecklistController::class, 'destroyItem'])->name('checklist-items.destroy');

    Route::post('tasks/{task}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});
