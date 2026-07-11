<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\TaskController;
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
});
