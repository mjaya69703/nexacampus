<?php

use App\Http\Controllers\AcademicLeader\ClassOversightController;
use App\Http\Controllers\AcademicLeader\DashboardController;
use App\Http\Controllers\AcademicLeader\EdomOversightController;
use App\Http\Controllers\AcademicLeader\LecturerOversightController;
use App\Http\Controllers\AcademicLeader\ReportOversightController;
use App\Http\Controllers\AcademicLeader\WorkloadOversightController;
use App\Http\Controllers\Organization\AcademicLeaderReportExportController;
use App\Http\Controllers\Organization\LecturerWorkloadExportController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::post('/approvals/steps/{step}/approve', [DashboardController::class, 'approveStep'])->name('approvals.steps.approve');
Route::post('/approvals/steps/{step}/reject', [DashboardController::class, 'rejectStep'])->name('approvals.steps.reject');
Route::get('/lecturers', [LecturerOversightController::class, 'index'])->name('lecturers.index');
Route::get('/workloads', [WorkloadOversightController::class, 'index'])->name('workloads.index');
Route::get('/workloads/export/{format}', [LecturerWorkloadExportController::class, 'academicLeader'])
    ->whereIn('format', ['csv', 'xlsx', 'pdf'])
    ->name('workloads.export');
Route::get('/classes', [ClassOversightController::class, 'index'])->name('classes.index');
Route::get('/edom', [EdomOversightController::class, 'index'])->name('edom.index');
Route::get('/reports', [ReportOversightController::class, 'index'])->name('reports.index');
Route::get('/reports/export/{type}/{format}', AcademicLeaderReportExportController::class)
    ->whereIn('type', ['lecturers', 'classes', 'alerts'])
    ->whereIn('format', ['csv', 'xlsx', 'pdf'])
    ->name('reports.export');
Route::get('/lecturers/{lecturerProfile}', [LecturerOversightController::class, 'show'])->name('lecturers.show');
