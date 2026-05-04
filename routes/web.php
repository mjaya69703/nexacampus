<?php

use App\Http\Controllers\AuthController;
use App\Support\ResourceRegistry;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('layouts.appv2');
// });

Route::livewire('/welcome', 'setup-wizard')->name('system.setup-wizard');

Route::middleware('is_installed')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::livewire('/auth/login', 'auth.signin-index')->name('auth.signin-index');
    });

    Route::middleware('auth')->group(function () {

        Route::livewire('/auth/select-role', 'auth.select-role')->name('auth.select-role');
        Route::livewire('/profile', 'profile-index')->name('home.profile-index');
        Route::get('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/switch-role', [AuthController::class, 'switchRole'])->name('auth.switch-role');
        // Route::livewire('/', 'admin.dashboard.index')->name('root.home-index');

        // Admin Routes
        Route::middleware('active_role')->prefix('admin')->as('admin.')->group(function () {
            foreach (ResourceRegistry::all() as $resource) {
                Route::crudLivewire(
                    $resource['plural'],
                    $resource['component'],
                    $resource['actions'],
                    $resource['area']
                );
            }

            Route::livewire('/academic/course-offerings/{offeringId}/attendance-sessions/{id}', 'admin.academic.attendance-sessions.show')
                ->middleware('active_permission:course-offering.view')
                ->name('academic.attendance-sessions.show');

            Route::livewire('/dashboard', 'admin.dashboard.index')->name('dashboard.index');
        });

        // Student Routes
        Route::middleware('active_role:student')->prefix('student')->as('student.')->group(function () {
            Route::livewire('/dashboard', 'student.dashboard.index')->name('dashboard.index');
            Route::livewire('/registration', 'student.registration.index')->name('registration.index');
            Route::livewire('/study-plan', 'student.study-plan.index')->name('study-plan.index');
            Route::livewire('/grades', 'student.grades.index')->name('grades.index');
            Route::livewire('/transcript', 'student.transcript.index')->name('transcript.index');
            Route::livewire('/schedule', 'student.schedule.index')->name('schedule.index');
            Route::livewire('/schedule/{offeringId}/attendance', 'student.schedule.attendance.index')->name('schedule.attendance');
            Route::livewire('/schedule/attendance/{sessionId}/record', 'student.schedule.attendance.record.index')->name('schedule.attendance.record');
        });

        // Lecturer Routes
        Route::middleware('active_role:lecturer')->prefix('lecturer')->as('lecturer.')->group(function () {
            Route::livewire('/dashboard', 'lecturer.dashboard.index')->name('dashboard.index');
            Route::livewire('/course-offerings', 'lecturer.course-offerings.index')->name('course-offerings.index');
            Route::livewire('/course-offerings/{id}', 'lecturer.course-offerings.show')->name('course-offerings.show');
            Route::livewire('/course-offerings/{offeringId}/students', 'lecturer.course-offerings.students')->name('course-offerings.students');
            Route::livewire('/course-offerings/{offeringId}/attendance', 'lecturer.course-offerings.attendance')->name('course-offerings.attendance');
            Route::livewire('/course-offerings/{offeringId}/grades', 'lecturer.course-offerings.grades')->name('course-offerings.grades');
            Route::livewire('/attendance-sessions', 'lecturer.attendance-sessions.index')->name('attendance-sessions.index');
            Route::livewire('/attendance-sessions/{sessionId}/edit', 'lecturer.attendance-sessions.edit')->name('attendance-sessions.edit');
            Route::livewire('/student-grades', 'lecturer.student-grades.index')->name('student-grades.index');
            Route::livewire('/student-grades/{id}/edit', 'lecturer.student-grades.edit')->name('student-grades.edit');
            Route::livewire('/students', 'lecturer.students.index')->name('students.index');
        });
    });

});

// // Access Management
// Route::crudLivewire('users', 'admin.access.users', ['index', 'create', 'edit', 'delete'], 'access');
// Route::crudLivewire('permissions', 'admin.access.permissions', ['index', 'create', 'edit', 'delete'], 'access');
// Route::crudLivewire('roles', 'admin.access.roles', ['index', 'create', 'edit', 'delete'], 'access');
// // System Management
// Route::crudLivewire('menus', 'admin.system.menus', ['index', 'create', 'edit', 'delete'], 'system');
// Route::crudLivewire('settings', 'admin.system.settings', ['index'], 'system');
// Route::crudLivewire('activity-logs', 'admin.system.activity-logs', ['index', 'show'], 'system');
// // Academic Management
// Route::crudLivewire('academic-years', 'admin.academic.academic-years', ['index', 'create', 'edit', 'delete'], 'academic');
