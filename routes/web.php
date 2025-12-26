<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\PackageUpdateController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\AutomationRunController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'role:admin|super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });

Route::middleware(['auth', 'permission:manage-tasks'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('tasks/{task}/comments', [TaskController::class, 'addComment'])->name('tasks.comments.store');
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
        Route::resource('tasks', TaskController::class);
    });

Route::middleware(['auth', 'role:super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('roles', \App\Http\Controllers\Admin\RoleManagementController::class)->except(['show']);
        Route::resource('permissions', \App\Http\Controllers\Admin\PermissionManagementController::class)->only(['index', 'store', 'destroy']);

        Route::prefix('package-updates')
            ->name('package-updates.')
            ->group(function () {
                Route::get('/', [PackageUpdateController::class, 'index'])->name('index');
                Route::post('/run', [PackageUpdateController::class, 'run'])->name('run');
            });
    });

Route::middleware(['auth', 'role:admin|automation|super-admin'])
    ->prefix('admin/automations')
    ->name('admin.automations.')
    ->group(function () {
        Route::get('/', [AutomationController::class, 'index'])->name('index');
        Route::get('/create', [AutomationController::class, 'create'])->name('create');
        Route::post('/', [AutomationController::class, 'store'])->name('store');
        Route::get('/{automation}/edit', [AutomationController::class, 'edit'])->name('edit');
        Route::put('/{automation}', [AutomationController::class, 'update'])->name('update');
        Route::delete('/{automation}', [AutomationController::class, 'destroy'])->name('destroy');
        Route::post('/{automation}/toggle', [AutomationController::class, 'toggle'])->name('toggle');
        Route::post('/{automation}/run', [AutomationRunController::class, 'run'])->name('run');
        Route::get('/{automation}/logs', [AutomationController::class, 'logs'])->name('logs');
        Route::get('/logs/{log}', [AutomationController::class, 'showLog'])->name('log.show');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
