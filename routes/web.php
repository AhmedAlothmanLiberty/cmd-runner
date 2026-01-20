<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\PackageUpdateController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\EasyEngineJobController;
use App\Http\Controllers\Admin\S3UploadJobController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\AutomationRunController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EasyEngineUploadController;
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

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/backlog', [TaskController::class, 'backlog'])
            ->middleware('role:admin|super-admin')
            ->name('tasks.backlog');
        Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show')->whereNumber('task');
        Route::get('tasks/{task}/attachments/{attachment}/preview', [TaskController::class, 'previewAttachment'])
            ->name('tasks.attachments.preview')
            ->whereNumber('task')
            ->whereNumber('attachment');
        Route::delete('tasks/{task}/attachments/{attachment}', [TaskController::class, 'destroyAttachment'])
            ->name('tasks.attachments.destroy')
            ->whereNumber('task')
            ->whereNumber('attachment');
        Route::get('tasks/{task}/attachments/{attachment}/download', [TaskController::class, 'downloadAttachment'])
            ->name('tasks.attachments.download')
            ->whereNumber('task')
            ->whereNumber('attachment');
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status')->whereNumber('task');
        Route::post('tasks/{task}/comments', [TaskController::class, 'addComment'])->name('tasks.comments.store')->whereNumber('task');
    });

Route::middleware('auth')->group(function () {
    Route::get('/notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
});

Route::middleware(['auth', 'permission:manage-tasks'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('tasks', TaskController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
    });

Route::middleware(['auth', 'role:super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('roles', \App\Http\Controllers\Admin\RoleManagementController::class)->except(['show']);
        Route::resource('permissions', \App\Http\Controllers\Admin\PermissionManagementController::class)->only(['index', 'store', 'destroy']);

        Route::prefix('s3-upload-jobs')
            ->name('s3-upload-jobs.')
            ->group(function () {
                Route::get('/', [S3UploadJobController::class, 'index'])->name('index');
                Route::get('/{job}', [S3UploadJobController::class, 'show'])->name('show')->whereNumber('job');
            });

        Route::prefix('easyengine-jobs')
            ->name('easyengine-jobs.')
            ->group(function () {
                Route::get('/', [EasyEngineJobController::class, 'index'])->name('index');
                Route::get('/{job}', [EasyEngineJobController::class, 'show'])->name('show')->whereNumber('job');
            });

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

Route::middleware(['web', 'internal.basic'])
    ->prefix('internal/easyengine')
    ->group(function () {
        Route::get('/upload', [EasyEngineUploadController::class, 'form'])->name('ee.upload.form');
        Route::post('/upload', [EasyEngineUploadController::class, 'upload'])->name('ee.upload');
    });

require __DIR__ . '/auth.php';
