<?php

use App\Http\Controllers\Organization\AcademicLeaderReportExportController;
use App\Http\Controllers\Organization\LecturerWorkloadExportController;
use Illuminate\Support\Facades\Route;

Route::livewire('/dashboard', 'academic-leader.dashboard.index')->name('dashboard.index');
Route::livewire('/lecturers', 'academic-leader.lecturers.index')->name('lecturers.index');
Route::livewire('/workloads', 'academic-leader.workloads.index')->name('workloads.index');
Route::get('/workloads/export/{format}', [LecturerWorkloadExportController::class, 'academicLeader'])
    ->whereIn('format', ['csv', 'xlsx', 'pdf'])
    ->name('workloads.export');
Route::livewire('/classes', 'academic-leader.classes.index')->name('classes.index');
Route::livewire('/edom', 'academic-leader.edom.index')->name('edom.index');
Route::livewire('/reports', 'academic-leader.reports.index')->name('reports.index');
Route::get('/reports/export/{type}/{format}', AcademicLeaderReportExportController::class)
    ->whereIn('type', ['lecturers', 'classes', 'alerts'])
    ->whereIn('format', ['csv', 'xlsx', 'pdf'])
    ->name('reports.export');
Route::livewire('/lecturers/{lecturerProfileId}', 'academic-leader.lecturers.show')->name('lecturers.show');
