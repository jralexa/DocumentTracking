<?php

use App\Http\Controllers\DepartmentMonthlyReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentQueueController;
use App\Http\Controllers\DocumentWorkflowController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'can:documents.process'])->group(function () {
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/queues', [DocumentQueueController::class, 'index'])->name('documents.queues.index');
    Route::post('/documents/{document}/accept', [DocumentWorkflowController::class, 'accept'])->name('documents.accept');
    Route::post('/documents/{document}/forward', [DocumentWorkflowController::class, 'forward'])->name('documents.forward');
    Route::post('/transfers/{transfer}/recall', [DocumentWorkflowController::class, 'recall'])->name('documents.recall');
});

Route::middleware(['auth', 'can:documents.export'])->group(function () {
    Route::get('/reports/departments/monthly', [DepartmentMonthlyReportController::class, 'index'])->name('reports.departments.monthly');
    Route::get('/reports/departments/monthly/export', [DepartmentMonthlyReportController::class, 'export'])->name('reports.departments.monthly.export');
});

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/departments', [AdminDepartmentController::class, 'index'])->name('departments.index');
        Route::get('/departments/create', [AdminDepartmentController::class, 'create'])->name('departments.create');
        Route::post('/departments', [AdminDepartmentController::class, 'store'])->name('departments.store');
        Route::get('/departments/{department}/edit', [AdminDepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('/departments/{department}', [AdminDepartmentController::class, 'update'])->name('departments.update');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

        Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');
    });

require __DIR__.'/auth.php';
