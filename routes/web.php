<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DayTaskController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest.roster')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth.roster')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/switch-user', [AuthController::class, 'switchUser'])->name('switch-user');

    Route::get('/', [RosterController::class, 'index'])->name('roster.index');
    Route::post('/roster/tasks', [DayTaskController::class, 'store'])->name('roster.tasks.store');
    Route::put('/roster/tasks/{dayTask}', [DayTaskController::class, 'update'])->name('roster.tasks.update');
    Route::delete('/roster/tasks/{dayTask}', [DayTaskController::class, 'destroy'])->name('roster.tasks.destroy');

    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::post('/requests', [RequestController::class, 'store'])->name('requests.store');

    Route::middleware('manager.only')->group(function () {
        Route::post('/roster/entries', [RosterController::class, 'upsert'])->name('roster.upsert');
        Route::post('/roster/autofill', [RosterController::class, 'autofill'])->name('roster.autofill');
        Route::get('/roster/export', [RosterController::class, 'export'])->name('roster.export');

        Route::post('/requests/{leaveRequest}/approve', [RequestController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{leaveRequest}/reject', [RequestController::class, 'reject'])->name('requests.reject');

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::put('/team/{user}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('/team/{user}', [TeamController::class, 'archive'])->name('team.archive');
    });
});
