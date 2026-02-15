<?php

use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\DistrictController as AdminDistrictController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SchoolController as AdminSchoolController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentMonthlyReportController;
use App\Http\Controllers\DocumentAnalyticsReportController;
use App\Http\Controllers\DocumentAttachmentController;
use App\Http\Controllers\DocumentCaseController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentCustodyController;
use App\Http\Controllers\DocumentListController;
use App\Http\Controllers\DocumentQueueController;
use App\Http\Controllers\DocumentSplitController;
use App\Http\Controllers\DocumentTrackController;
use App\Http\Controllers\DocumentWorkflowController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/track', [DocumentTrackController::class, 'public'])->name('documents.track.public');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'password.changed', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'password.changed', 'can:documents.intake'])->group(function () {
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
});

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/documents/track', [DocumentTrackController::class, 'index'])->name('documents.track');
});

Route::middleware(['auth', 'password.changed', 'can:documents.process'])->group(function () {
    Route::get('/documents/queues', [DocumentQueueController::class, 'index'])->name('documents.queues.index');
    Route::post('/documents/{document}/accept', [DocumentWorkflowController::class, 'accept'])->name('documents.accept');
    Route::post('/documents/{document}/forward', [DocumentWorkflowController::class, 'forward'])->name('documents.forward');
    Route::post('/documents/{document}/complete', [DocumentWorkflowController::class, 'complete'])->name('documents.complete');
    Route::post('/transfers/{transfer}/recall', [DocumentWorkflowController::class, 'recall'])->name('documents.recall');
    Route::get('/documents/{document}/split', [DocumentSplitController::class, 'create'])->name('documents.split.create');
    Route::post('/documents/{document}/split', [DocumentSplitController::class, 'store'])->name('documents.split.store');

    Route::prefix('custody')->name('custody.')->group(function () {
        Route::get('/originals', [DocumentCustodyController::class, 'originals'])->name('originals.index');
        Route::post('/originals/{document}/release', [DocumentCustodyController::class, 'releaseOriginal'])->name('originals.release');
        Route::get('/copies', [DocumentCustodyController::class, 'copies'])->name('copies.index');
    });
});

Route::middleware(['auth', 'password.changed', 'can:documents.view'])->group(function () {
    Route::get('/documents', [DocumentListController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/attachments/{attachment}', [DocumentAttachmentController::class, 'download'])->name('documents.attachments.download');
    Route::get('/cases', [DocumentCaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/{documentCase}', [DocumentCaseController::class, 'show'])->name('cases.show');
});

Route::middleware(['auth', 'password.changed', 'can:documents.manage'])->group(function () {
    Route::prefix('custody')->name('custody.')->group(function () {
        Route::get('/returnables', [DocumentCustodyController::class, 'returnables'])->name('returnables.index');
        Route::post('/returnables/{document}/returned', [DocumentCustodyController::class, 'markReturned'])->name('returnables.returned');
    });
});

Route::middleware(['auth', 'password.changed', 'can:documents.export'])->group(function () {
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', fn () => redirect()->route('reports.departments.monthly'))->name('index');

        Route::prefix('departments')->name('departments.')->group(function () {
            Route::get('/monthly', [DepartmentMonthlyReportController::class, 'index'])->name('monthly');
            Route::get('/monthly/export', [DepartmentMonthlyReportController::class, 'export'])->name('monthly.export');
        });

        Route::get('/aging-overdue', [DocumentAnalyticsReportController::class, 'aging'])->name('aging-overdue');
        Route::get('/sla-compliance', [DocumentAnalyticsReportController::class, 'slaCompliance'])->name('sla-compliance');
        Route::get('/performance', [DocumentAnalyticsReportController::class, 'performance'])->name('performance');
        Route::get('/custody', [DocumentAnalyticsReportController::class, 'custody'])->name('custody');
    });
});

Route::middleware(['auth', 'password.changed', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::prefix('departments')->name('departments.')->group(function () {
            Route::get('/', [AdminDepartmentController::class, 'index'])->name('index');
            Route::get('/create', [AdminDepartmentController::class, 'create'])->name('create');
            Route::post('/', [AdminDepartmentController::class, 'store'])->name('store');
            Route::get('/{department}/edit', [AdminDepartmentController::class, 'edit'])->name('edit');
            Route::put('/{department}', [AdminDepartmentController::class, 'update'])->name('update');
            Route::delete('/{department}', [AdminDepartmentController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('districts')->name('districts.')->group(function () {
            Route::get('/', [AdminDistrictController::class, 'index'])->name('index');
            Route::get('/create', [AdminDistrictController::class, 'create'])->name('create');
            Route::post('/', [AdminDistrictController::class, 'store'])->name('store');
            Route::get('/{district}/edit', [AdminDistrictController::class, 'edit'])->name('edit');
            Route::put('/{district}', [AdminDistrictController::class, 'update'])->name('update');
            Route::delete('/{district}', [AdminDistrictController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('schools')->name('schools.')->group(function () {
            Route::get('/', [AdminSchoolController::class, 'index'])->name('index');
            Route::get('/create', [AdminSchoolController::class, 'create'])->name('create');
            Route::post('/', [AdminSchoolController::class, 'store'])->name('store');
            Route::get('/{school}/edit', [AdminSchoolController::class, 'edit'])->name('edit');
            Route::put('/{school}', [AdminSchoolController::class, 'update'])->name('update');
            Route::delete('/{school}', [AdminSchoolController::class, 'destroy'])->name('destroy');
        });

        Route::get('/organization', [AdminOrganizationController::class, 'index'])->name('organization.index');

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::get('/create', [UserManagementController::class, 'create'])->name('create');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');
            Route::post('/{user}/reset-password', [UserManagementController::class, 'resetTemporaryPassword'])->name('reset-password');
            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
        });

        Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');
    });

require __DIR__.'/auth.php';
