<?php

use App\Http\Controllers\Admin\Access\PermissionController;
use App\Http\Controllers\Admin\Access\RoleController;
use App\Http\Controllers\Admin\Access\UserController;
use App\Http\Controllers\Admin\System\ActivityLogController;
use App\Http\Controllers\Admin\System\MenuController;
use App\Http\Controllers\Admin\System\NotificationLogController;
use App\Http\Controllers\Admin\System\SettingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Academic\AdminAcademicExportController;
use App\Http\Controllers\Admin\Academic\AcademicPeriodController;
use App\Http\Controllers\Admin\Academic\AcademicYearController;
use App\Http\Controllers\Admin\Academic\AcademicAdvisorAssignmentController;
use App\Http\Controllers\Admin\Academic\AttendanceSessionController;
use App\Http\Controllers\Admin\Academic\CourseController;
use App\Http\Controllers\Admin\Academic\CourseOfferingController;
use App\Http\Controllers\Admin\Academic\CourseScheduleController;
use App\Http\Controllers\Admin\Academic\CurriculumController;
use App\Http\Controllers\Admin\Academic\FacultyController;
use App\Http\Controllers\Admin\Academic\StudyProgramController;
use App\Http\Controllers\Admin\Admission\AcceptanceLetterController;
use App\Http\Controllers\Admin\Admission\AdmissionDocumentController;
use App\Http\Controllers\Alumni\AlumniConversionController;
use App\Http\Controllers\Alumni\TracerStudyAnalyticsController;
use App\Http\Controllers\Financial\FinancialReportExportController;
use App\Http\Controllers\Financial\PaymentReceiptController;
use App\Http\Controllers\Organization\LecturerWorkloadExportController;
use App\Http\Controllers\Organization\TridharmaAttachmentController;
use App\Http\Controllers\Organization\UserDevelopmentAttachmentController;
use App\Http\Controllers\StudentService\ComplaintAttachmentController;
use App\Http\Controllers\StudentService\GraduationDocumentController;
use App\Http\Controllers\StudentService\ServiceLetterDownloadController;
use App\Support\ResourceRegistry;
use Illuminate\Support\Facades\Route;

foreach (ResourceRegistry::all() as $resource) {
    // users, permissions, roles, menus, settings, faculties, notification-logs & activity-logs → Inertia React.
    if (in_array($resource['plural'], ['permissions', 'roles', 'users', 'menus', 'settings', 'faculties', 'study-programs', 'courses', 'academic-years', 'academic-periods', 'academic-advisor-assignments', 'curriculums', 'course-offerings', 'course-schedules', 'notification-logs', 'activity-logs']) && in_array($resource['area'], ['access', 'system', 'academic'])) {
        continue;
    }

    Route::crudLivewire(
        $resource['plural'],
        $resource['component'],
        $resource['actions'],
        $resource['area'],
        $resource['resource']
    );
}

// CRUD Hak Akses (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/access/permissions/export', [PermissionController::class, 'export'])
    ->middleware('active_permission:permission.viewAny')
    ->name('access.permissions.export');
Route::post('/access/permissions/bulk-destroy', [PermissionController::class, 'bulkDestroy'])
    ->middleware('active_permission:permission.delete')
    ->name('access.permissions.bulk-destroy');
Route::post('/access/permissions/bulk-restore', [PermissionController::class, 'bulkRestore'])
    ->middleware('active_permission:permission.update|permission.delete')
    ->name('access.permissions.bulk-restore');
Route::post('/access/permissions/bulk-force-destroy', [PermissionController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:permission.update|permission.delete')
    ->name('access.permissions.bulk-force-destroy');
Route::post('/access/permissions/{id}/restore', [PermissionController::class, 'restore'])
    ->middleware('active_permission:permission.update|permission.delete')
    ->name('access.permissions.restore');
Route::delete('/access/permissions/{id}/force', [PermissionController::class, 'forceDestroy'])
    ->middleware('active_permission:permission.update|permission.delete')
    ->name('access.permissions.force-destroy');

// CRUD Peran (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/access/roles/export', [RoleController::class, 'export'])
    ->middleware('active_permission:role.viewAny')
    ->name('access.roles.export');
Route::get('/access/roles/import/template', [RoleController::class, 'importTemplate'])
    ->middleware('active_permission:role.create')
    ->name('access.roles.import-template');
Route::post('/access/roles/import', [RoleController::class, 'import'])
    ->middleware('active_permission:role.create')
    ->name('access.roles.import');
Route::post('/access/roles/bulk-destroy', [RoleController::class, 'bulkDestroy'])
    ->middleware('active_permission:role.delete')
    ->name('access.roles.bulk-destroy');
Route::post('/access/roles/bulk-restore', [RoleController::class, 'bulkRestore'])
    ->middleware('active_permission:role.update|role.delete')
    ->name('access.roles.bulk-restore');
Route::post('/access/roles/bulk-force-destroy', [RoleController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:role.update|role.delete')
    ->name('access.roles.bulk-force-destroy');
Route::post('/access/roles/{id}/restore', [RoleController::class, 'restore'])
    ->middleware('active_permission:role.update|role.delete')
    ->name('access.roles.restore');
Route::delete('/access/roles/{id}/force', [RoleController::class, 'forceDestroy'])
    ->middleware('active_permission:role.update|role.delete')
    ->name('access.roles.force-destroy');
Route::get('/access/roles', [RoleController::class, 'index'])
    ->middleware('active_permission:role.viewAny')
    ->name('access.roles.index');
Route::get('/access/roles/create', [RoleController::class, 'create'])
    ->middleware('active_permission:role.create')
    ->name('access.roles.create');
Route::post('/access/roles', [RoleController::class, 'store'])
    ->middleware('active_permission:role.create')
    ->name('access.roles.store');
Route::get('/access/roles/{role}/edit', [RoleController::class, 'edit'])
    ->middleware('active_permission:role.update')
    ->name('access.roles.edit');
Route::put('/access/roles/{role}', [RoleController::class, 'update'])
    ->middleware('active_permission:role.update')
    ->name('access.roles.update');
Route::delete('/access/roles/{role}', [RoleController::class, 'destroy'])
    ->middleware('active_permission:role.delete')
    ->name('access.roles.destroy');

// CRUD Pengguna (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/access/users/export', [UserController::class, 'export'])
    ->middleware('active_permission:user.viewAny')
    ->name('access.users.export');
Route::get('/access/users/import/template', [UserController::class, 'importTemplate'])
    ->middleware('active_permission:user.create')
    ->name('access.users.import-template');
Route::post('/access/users/import', [UserController::class, 'import'])
    ->middleware('active_permission:user.create')
    ->name('access.users.import');
Route::post('/access/users/bulk-destroy', [UserController::class, 'bulkDestroy'])
    ->middleware('active_permission:user.delete')
    ->name('access.users.bulk-destroy');
Route::post('/access/users/bulk-restore', [UserController::class, 'bulkRestore'])
    ->middleware('active_permission:user.update|user.delete')
    ->name('access.users.bulk-restore');
Route::post('/access/users/bulk-force-destroy', [UserController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:user.update|user.delete')
    ->name('access.users.bulk-force-destroy');
Route::delete('/access/users/{id}/force', [UserController::class, 'forceDestroy'])
    ->middleware('active_permission:user.update|user.delete')
    ->name('access.users.force-destroy');
Route::post('/access/users/{id}/restore', [UserController::class, 'restore'])
    ->middleware('active_permission:user.update|user.delete')
    ->name('access.users.restore');
Route::post('/access/users/{user}/toggle', [UserController::class, 'toggle'])
    ->middleware('active_permission:user.update')
    ->name('access.users.toggle');
Route::get('/access/users', [UserController::class, 'index'])
    ->middleware('active_permission:user.viewAny')
    ->name('access.users.index');
Route::get('/access/users/create', [UserController::class, 'create'])
    ->middleware('active_permission:user.create')
    ->name('access.users.create');
Route::post('/access/users', [UserController::class, 'store'])
    ->middleware('active_permission:user.create')
    ->name('access.users.store');
Route::get('/access/users/{user}/edit', [UserController::class, 'edit'])
    ->middleware('active_permission:user.update')
    ->name('access.users.edit');
Route::put('/access/users/{user}', [UserController::class, 'update'])
    ->middleware('active_permission:user.update')
    ->name('access.users.update');
Route::delete('/access/users/{user}', [UserController::class, 'destroy'])
    ->middleware('active_permission:user.delete')
    ->name('access.users.destroy');

// CRUD Menu (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/system/menus/export', [MenuController::class, 'export'])
    ->middleware('active_permission:menu.viewAny')
    ->name('system.menus.export');
Route::post('/system/menus/bulk-destroy', [MenuController::class, 'bulkDestroy'])
    ->middleware('active_permission:menu.delete')
    ->name('system.menus.bulk-destroy');
Route::post('/system/menus/bulk-restore', [MenuController::class, 'bulkRestore'])
    ->middleware('active_permission:menu.update|menu.delete')
    ->name('system.menus.bulk-restore');
Route::post('/system/menus/bulk-force-destroy', [MenuController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:menu.update|menu.delete')
    ->name('system.menus.bulk-force-destroy');
Route::post('/system/menus/{id}/restore', [MenuController::class, 'restore'])
    ->middleware('active_permission:menu.update|menu.delete')
    ->name('system.menus.restore');
Route::delete('/system/menus/{id}/force', [MenuController::class, 'forceDestroy'])
    ->middleware('active_permission:menu.update|menu.delete')
    ->name('system.menus.force-destroy');
Route::get('/system/menus', [MenuController::class, 'index'])
    ->middleware('active_permission:menu.viewAny')
    ->name('system.menus.index');
Route::get('/system/menus/create', [MenuController::class, 'create'])
    ->middleware('active_permission:menu.create')
    ->name('system.menus.create');
Route::post('/system/menus', [MenuController::class, 'store'])
    ->middleware('active_permission:menu.create')
    ->name('system.menus.store');
Route::get('/system/menus/{menu}/edit', [MenuController::class, 'edit'])
    ->middleware('active_permission:menu.update')
    ->name('system.menus.edit');
Route::put('/system/menus/{menu}', [MenuController::class, 'update'])
    ->middleware('active_permission:menu.update')
    ->name('system.menus.update');
Route::delete('/system/menus/{menu}', [MenuController::class, 'destroy'])
    ->middleware('active_permission:menu.delete')
    ->name('system.menus.destroy');

// Pengaturan Sistem (Inertia React, singleton) — paritas Livewire.
Route::get('/system/settings', [SettingController::class, 'index'])
    ->middleware('active_permission:setting.viewAny')
    ->name('system.settings.index');
Route::put('/system/settings', [SettingController::class, 'update'])
    ->middleware('active_permission:setting.viewAny')
    ->name('system.settings.update');
Route::post('/system/settings/check-health', [SettingController::class, 'checkHealth'])
    ->middleware('active_permission:setting.viewAny')
    ->name('system.settings.check-health');
Route::post('/system/settings/test-whatsapp', [SettingController::class, 'sendTestWhatsapp'])
    ->middleware('active_permission:setting.viewAny')
    ->name('system.settings.test-whatsapp');
Route::post('/system/settings/test-push', [SettingController::class, 'sendTestPush'])
    ->middleware('active_permission:setting.viewAny')
    ->name('system.settings.test-push');
Route::post('/system/settings/sidecar-start', [SettingController::class, 'sidecarStart'])
    ->middleware('active_permission:setting.viewAny')
    ->name('system.settings.sidecar-start');
Route::post('/system/settings/sidecar-stop', [SettingController::class, 'sidecarStop'])
    ->middleware('active_permission:setting.viewAny')
    ->name('system.settings.sidecar-stop');
Route::post('/system/settings/sidecar-refresh', [SettingController::class, 'sidecarRefresh'])
    ->middleware('active_permission:setting.viewAny')
    ->name('system.settings.sidecar-refresh');
Route::post('/system/settings/use-session', [SettingController::class, 'useSession'])
    ->middleware('active_permission:setting.viewAny')
    ->name('system.settings.use-session');

// CRUD Fakultas (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/academic/faculties/export', [FacultyController::class, 'export'])
    ->middleware('active_permission:faculty.viewAny')
    ->name('academic.faculties.export');
Route::get('/academic/faculties/import/template', [FacultyController::class, 'importTemplate'])
    ->middleware('active_permission:faculty.create')
    ->name('academic.faculties.import-template');
Route::post('/academic/faculties/import', [FacultyController::class, 'import'])
    ->middleware('active_permission:faculty.create')
    ->name('academic.faculties.import');
Route::post('/academic/faculties/bulk-destroy', [FacultyController::class, 'bulkDestroy'])
    ->middleware('active_permission:faculty.delete')
    ->name('academic.faculties.bulk-destroy');
Route::post('/academic/faculties/bulk-restore', [FacultyController::class, 'bulkRestore'])
    ->middleware('active_permission:faculty.update|faculty.delete')
    ->name('academic.faculties.bulk-restore');
Route::post('/academic/faculties/bulk-force-destroy', [FacultyController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:faculty.update|faculty.delete')
    ->name('academic.faculties.bulk-force-destroy');
Route::post('/academic/faculties/{id}/restore', [FacultyController::class, 'restore'])
    ->middleware('active_permission:faculty.update|faculty.delete')
    ->name('academic.faculties.restore');
Route::delete('/academic/faculties/{id}/force', [FacultyController::class, 'forceDestroy'])
    ->middleware('active_permission:faculty.update|faculty.delete')
    ->name('academic.faculties.force-destroy');
Route::post('/academic/faculties/{faculty}/toggle', [FacultyController::class, 'toggle'])
    ->middleware('active_permission:faculty.update')
    ->name('academic.faculties.toggle');
Route::get('/academic/faculties', [FacultyController::class, 'index'])
    ->middleware('active_permission:faculty.viewAny')
    ->name('academic.faculties.index');
Route::get('/academic/faculties/create', [FacultyController::class, 'create'])
    ->middleware('active_permission:faculty.create')
    ->name('academic.faculties.create');
Route::post('/academic/faculties', [FacultyController::class, 'store'])
    ->middleware('active_permission:faculty.create')
    ->name('academic.faculties.store');
Route::get('/academic/faculties/{faculty}/edit', [FacultyController::class, 'edit'])
    ->middleware('active_permission:faculty.update')
    ->name('academic.faculties.edit');
Route::put('/academic/faculties/{faculty}', [FacultyController::class, 'update'])
    ->middleware('active_permission:faculty.update')
    ->name('academic.faculties.update');
Route::delete('/academic/faculties/{faculty}', [FacultyController::class, 'destroy'])
    ->middleware('active_permission:faculty.delete')
    ->name('academic.faculties.destroy');

// CRUD Program Studi (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/academic/study-programs/export', [StudyProgramController::class, 'export'])
    ->middleware('active_permission:study-program.viewAny')
    ->name('academic.study-programs.export');
Route::get('/academic/study-programs/import/template', [StudyProgramController::class, 'importTemplate'])
    ->middleware('active_permission:study-program.create')
    ->name('academic.study-programs.import-template');
Route::post('/academic/study-programs/import', [StudyProgramController::class, 'import'])
    ->middleware('active_permission:study-program.create')
    ->name('academic.study-programs.import');
Route::post('/academic/study-programs/bulk-destroy', [StudyProgramController::class, 'bulkDestroy'])
    ->middleware('active_permission:study-program.delete')
    ->name('academic.study-programs.bulk-destroy');
Route::post('/academic/study-programs/bulk-restore', [StudyProgramController::class, 'bulkRestore'])
    ->middleware('active_permission:study-program.update|study-program.delete')
    ->name('academic.study-programs.bulk-restore');
Route::post('/academic/study-programs/bulk-force-destroy', [StudyProgramController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:study-program.update|study-program.delete')
    ->name('academic.study-programs.bulk-force-destroy');
Route::post('/academic/study-programs/{id}/restore', [StudyProgramController::class, 'restore'])
    ->middleware('active_permission:study-program.update|study-program.delete')
    ->name('academic.study-programs.restore');
Route::delete('/academic/study-programs/{id}/force', [StudyProgramController::class, 'forceDestroy'])
    ->middleware('active_permission:study-program.update|study-program.delete')
    ->name('academic.study-programs.force-destroy');
Route::post('/academic/study-programs/{studyProgram}/toggle', [StudyProgramController::class, 'toggle'])
    ->middleware('active_permission:study-program.update')
    ->name('academic.study-programs.toggle');
Route::get('/academic/study-programs', [StudyProgramController::class, 'index'])
    ->middleware('active_permission:study-program.viewAny')
    ->name('academic.study-programs.index');
Route::get('/academic/study-programs/create', [StudyProgramController::class, 'create'])
    ->middleware('active_permission:study-program.create')
    ->name('academic.study-programs.create');
Route::post('/academic/study-programs', [StudyProgramController::class, 'store'])
    ->middleware('active_permission:study-program.create')
    ->name('academic.study-programs.store');
Route::get('/academic/study-programs/{studyProgram}/edit', [StudyProgramController::class, 'edit'])
    ->middleware('active_permission:study-program.update')
    ->name('academic.study-programs.edit');
Route::put('/academic/study-programs/{studyProgram}', [StudyProgramController::class, 'update'])
    ->middleware('active_permission:study-program.update')
    ->name('academic.study-programs.update');
Route::delete('/academic/study-programs/{studyProgram}', [StudyProgramController::class, 'destroy'])
    ->middleware('active_permission:study-program.delete')
    ->name('academic.study-programs.destroy');

// CRUD Mata Kuliah (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/academic/courses/export', [CourseController::class, 'export'])
    ->middleware('active_permission:course.viewAny')
    ->name('academic.courses.export');
Route::get('/academic/courses/import/template', [CourseController::class, 'importTemplate'])
    ->middleware('active_permission:course.create')
    ->name('academic.courses.import-template');
Route::post('/academic/courses/import', [CourseController::class, 'import'])
    ->middleware('active_permission:course.create')
    ->name('academic.courses.import');
Route::post('/academic/courses/bulk-destroy', [CourseController::class, 'bulkDestroy'])
    ->middleware('active_permission:course.delete')
    ->name('academic.courses.bulk-destroy');
Route::post('/academic/courses/bulk-restore', [CourseController::class, 'bulkRestore'])
    ->middleware('active_permission:course.update|course.delete')
    ->name('academic.courses.bulk-restore');
Route::post('/academic/courses/bulk-force-destroy', [CourseController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:course.update|course.delete')
    ->name('academic.courses.bulk-force-destroy');
Route::post('/academic/courses/{id}/restore', [CourseController::class, 'restore'])
    ->middleware('active_permission:course.update|course.delete')
    ->name('academic.courses.restore');
Route::delete('/academic/courses/{id}/force', [CourseController::class, 'forceDestroy'])
    ->middleware('active_permission:course.update|course.delete')
    ->name('academic.courses.force-destroy');
Route::post('/academic/courses/{course}/toggle', [CourseController::class, 'toggle'])
    ->middleware('active_permission:course.update')
    ->name('academic.courses.toggle');
Route::get('/academic/courses', [CourseController::class, 'index'])
    ->middleware('active_permission:course.viewAny')
    ->name('academic.courses.index');
Route::get('/academic/courses/create', [CourseController::class, 'create'])
    ->middleware('active_permission:course.create')
    ->name('academic.courses.create');
Route::post('/academic/courses', [CourseController::class, 'store'])
    ->middleware('active_permission:course.create')
    ->name('academic.courses.store');
Route::get('/academic/courses/{course}/edit', [CourseController::class, 'edit'])
    ->middleware('active_permission:course.update')
    ->name('academic.courses.edit');
Route::put('/academic/courses/{course}', [CourseController::class, 'update'])
    ->middleware('active_permission:course.update')
    ->name('academic.courses.update');
Route::delete('/academic/courses/{course}', [CourseController::class, 'destroy'])
    ->middleware('active_permission:course.delete')
    ->name('academic.courses.destroy');

// CRUD Tahun Akademik (Inertia React) — paritas Livewire.
Route::get('/academic/academic-years/export', [AcademicYearController::class, 'export'])
    ->middleware('active_permission:academic-year.viewAny')
    ->name('academic.academic-years.export');
Route::get('/academic/academic-years/import/template', [AcademicYearController::class, 'importTemplate'])
    ->middleware('active_permission:academic-year.create')
    ->name('academic.academic-years.import-template');
Route::post('/academic/academic-years/import', [AcademicYearController::class, 'import'])
    ->middleware('active_permission:academic-year.create')
    ->name('academic.academic-years.import');
Route::post('/academic/academic-years/bulk-destroy', [AcademicYearController::class, 'bulkDestroy'])
    ->middleware('active_permission:academic-year.delete')
    ->name('academic.academic-years.bulk-destroy');
Route::post('/academic/academic-years/bulk-restore', [AcademicYearController::class, 'bulkRestore'])
    ->middleware('active_permission:academic-year.update|academic-year.delete')
    ->name('academic.academic-years.bulk-restore');
Route::post('/academic/academic-years/bulk-force-destroy', [AcademicYearController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:academic-year.update|academic-year.delete')
    ->name('academic.academic-years.bulk-force-destroy');
Route::post('/academic/academic-years/{id}/restore', [AcademicYearController::class, 'restore'])
    ->middleware('active_permission:academic-year.update|academic-year.delete')
    ->name('academic.academic-years.restore');
Route::delete('/academic/academic-years/{id}/force', [AcademicYearController::class, 'forceDestroy'])
    ->middleware('active_permission:academic-year.update|academic-year.delete')
    ->name('academic.academic-years.force-destroy');
Route::post('/academic/academic-years/{academicYear}/toggle', [AcademicYearController::class, 'toggle'])
    ->middleware('active_permission:academic-year.update')
    ->name('academic.academic-years.toggle');
Route::get('/academic/academic-years', [AcademicYearController::class, 'index'])
    ->middleware('active_permission:academic-year.viewAny')
    ->name('academic.academic-years.index');
Route::get('/academic/academic-years/create', [AcademicYearController::class, 'create'])
    ->middleware('active_permission:academic-year.create')
    ->name('academic.academic-years.create');
Route::post('/academic/academic-years', [AcademicYearController::class, 'store'])
    ->middleware('active_permission:academic-year.create')
    ->name('academic.academic-years.store');
Route::get('/academic/academic-years/{academicYear}/edit', [AcademicYearController::class, 'edit'])
    ->middleware('active_permission:academic-year.update')
    ->name('academic.academic-years.edit');
Route::put('/academic/academic-years/{academicYear}', [AcademicYearController::class, 'update'])
    ->middleware('active_permission:academic-year.update')
    ->name('academic.academic-years.update');
Route::delete('/academic/academic-years/{academicYear}', [AcademicYearController::class, 'destroy'])
    ->middleware('active_permission:academic-year.delete')
    ->name('academic.academic-years.destroy');

// CRUD Periode Akademik (Inertia React) — paritas Livewire.
Route::get('/academic/academic-periods/export', [AcademicPeriodController::class, 'export'])
    ->middleware('active_permission:academic-period.viewAny')
    ->name('academic.academic-periods.export');
Route::get('/academic/academic-periods/import/template', [AcademicPeriodController::class, 'importTemplate'])
    ->middleware('active_permission:academic-period.create')
    ->name('academic.academic-periods.import-template');
Route::post('/academic/academic-periods/import', [AcademicPeriodController::class, 'import'])
    ->middleware('active_permission:academic-period.create')
    ->name('academic.academic-periods.import');
Route::post('/academic/academic-periods/bulk-destroy', [AcademicPeriodController::class, 'bulkDestroy'])
    ->middleware('active_permission:academic-period.delete')
    ->name('academic.academic-periods.bulk-destroy');
Route::post('/academic/academic-periods/bulk-restore', [AcademicPeriodController::class, 'bulkRestore'])
    ->middleware('active_permission:academic-period.update|academic-period.delete')
    ->name('academic.academic-periods.bulk-restore');
Route::post('/academic/academic-periods/bulk-force-destroy', [AcademicPeriodController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:academic-period.update|academic-period.delete')
    ->name('academic.academic-periods.bulk-force-destroy');
Route::post('/academic/academic-periods/{id}/restore', [AcademicPeriodController::class, 'restore'])
    ->middleware('active_permission:academic-period.update|academic-period.delete')
    ->name('academic.academic-periods.restore');
Route::delete('/academic/academic-periods/{id}/force', [AcademicPeriodController::class, 'forceDestroy'])
    ->middleware('active_permission:academic-period.update|academic-period.delete')
    ->name('academic.academic-periods.force-destroy');
Route::post('/academic/academic-periods/{academicPeriod}/toggle', [AcademicPeriodController::class, 'toggle'])
    ->middleware('active_permission:academic-period.update')
    ->name('academic.academic-periods.toggle');
Route::get('/academic/academic-periods', [AcademicPeriodController::class, 'index'])
    ->middleware('active_permission:academic-period.viewAny')
    ->name('academic.academic-periods.index');
Route::get('/academic/academic-periods/create', [AcademicPeriodController::class, 'create'])
    ->middleware('active_permission:academic-period.create')
    ->name('academic.academic-periods.create');
Route::post('/academic/academic-periods', [AcademicPeriodController::class, 'store'])
    ->middleware('active_permission:academic-period.create')
    ->name('academic.academic-periods.store');
Route::get('/academic/academic-periods/{academicPeriod}/edit', [AcademicPeriodController::class, 'edit'])
    ->middleware('active_permission:academic-period.update')
    ->name('academic.academic-periods.edit');
Route::put('/academic/academic-periods/{academicPeriod}', [AcademicPeriodController::class, 'update'])
    ->middleware('active_permission:academic-period.update')
    ->name('academic.academic-periods.update');
Route::delete('/academic/academic-periods/{academicPeriod}', [AcademicPeriodController::class, 'destroy'])
    ->middleware('active_permission:academic-period.delete')
    ->name('academic.academic-periods.destroy');

// CRUD Dosen Wali (Inertia React) — paritas Livewire + transfer massal.
Route::get('/academic/academic-advisor-assignments/export', [AcademicAdvisorAssignmentController::class, 'export'])
    ->middleware('active_permission:academic-advisor-assignment.viewAny')
    ->name('academic.academic-advisor-assignments.export');
Route::get('/academic/academic-advisor-assignments/import/template', [AcademicAdvisorAssignmentController::class, 'importTemplate'])
    ->middleware('active_permission:academic-advisor-assignment.create')
    ->name('academic.academic-advisor-assignments.import-template');
Route::post('/academic/academic-advisor-assignments/import', [AcademicAdvisorAssignmentController::class, 'import'])
    ->middleware('active_permission:academic-advisor-assignment.create')
    ->name('academic.academic-advisor-assignments.import');
Route::get('/academic/academic-advisor-assignments/search-students', [AcademicAdvisorAssignmentController::class, 'searchStudents'])
    ->middleware('active_permission:academic-advisor-assignment.viewAny')
    ->name('academic.academic-advisor-assignments.search-students');
Route::get('/academic/academic-advisor-assignments/search-lecturers', [AcademicAdvisorAssignmentController::class, 'searchLecturers'])
    ->middleware('active_permission:academic-advisor-assignment.viewAny')
    ->name('academic.academic-advisor-assignments.search-lecturers');
Route::get('/academic/academic-advisor-assignments/browse-students', [AcademicAdvisorAssignmentController::class, 'browseStudents'])
    ->middleware('active_permission:academic-advisor-assignment.viewAny')
    ->name('academic.academic-advisor-assignments.browse-students');
Route::post('/academic/academic-advisor-assignments/bulk-destroy', [AcademicAdvisorAssignmentController::class, 'bulkDestroy'])
    ->middleware('active_permission:academic-advisor-assignment.delete')
    ->name('academic.academic-advisor-assignments.bulk-destroy');
Route::post('/academic/academic-advisor-assignments/bulk-restore', [AcademicAdvisorAssignmentController::class, 'bulkRestore'])
    ->middleware('active_permission:academic-advisor-assignment.update|academic-advisor-assignment.delete')
    ->name('academic.academic-advisor-assignments.bulk-restore');
Route::post('/academic/academic-advisor-assignments/bulk-force-destroy', [AcademicAdvisorAssignmentController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:academic-advisor-assignment.update|academic-advisor-assignment.delete')
    ->name('academic.academic-advisor-assignments.bulk-force-destroy');
Route::post('/academic/academic-advisor-assignments/transfer', [AcademicAdvisorAssignmentController::class, 'transfer'])
    ->middleware('active_permission:academic-advisor-assignment.update')
    ->name('academic.academic-advisor-assignments.transfer');
Route::post('/academic/academic-advisor-assignments/{id}/restore', [AcademicAdvisorAssignmentController::class, 'restore'])
    ->middleware('active_permission:academic-advisor-assignment.update|academic-advisor-assignment.delete')
    ->name('academic.academic-advisor-assignments.restore');
Route::delete('/academic/academic-advisor-assignments/{id}/force', [AcademicAdvisorAssignmentController::class, 'forceDestroy'])
    ->middleware('active_permission:academic-advisor-assignment.update|academic-advisor-assignment.delete')
    ->name('academic.academic-advisor-assignments.force-destroy');
Route::post('/academic/academic-advisor-assignments/{academicAdvisorAssignment}/toggle', [AcademicAdvisorAssignmentController::class, 'toggle'])
    ->middleware('active_permission:academic-advisor-assignment.update')
    ->name('academic.academic-advisor-assignments.toggle');
Route::get('/academic/academic-advisor-assignments', [AcademicAdvisorAssignmentController::class, 'index'])
    ->middleware('active_permission:academic-advisor-assignment.viewAny')
    ->name('academic.academic-advisor-assignments.index');
Route::get('/academic/academic-advisor-assignments/create', [AcademicAdvisorAssignmentController::class, 'create'])
    ->middleware('active_permission:academic-advisor-assignment.create')
    ->name('academic.academic-advisor-assignments.create');
Route::post('/academic/academic-advisor-assignments', [AcademicAdvisorAssignmentController::class, 'store'])
    ->middleware('active_permission:academic-advisor-assignment.create')
    ->name('academic.academic-advisor-assignments.store');
Route::get('/academic/academic-advisor-assignments/{academicAdvisorAssignment}/edit', [AcademicAdvisorAssignmentController::class, 'edit'])
    ->middleware('active_permission:academic-advisor-assignment.update')
    ->name('academic.academic-advisor-assignments.edit');
Route::put('/academic/academic-advisor-assignments/{academicAdvisorAssignment}', [AcademicAdvisorAssignmentController::class, 'update'])
    ->middleware('active_permission:academic-advisor-assignment.update')
    ->name('academic.academic-advisor-assignments.update');
Route::get('/academic/academic-advisor-assignments/{id}', [AcademicAdvisorAssignmentController::class, 'show'])
    ->middleware('active_permission:academic-advisor-assignment.view')
    ->name('academic.academic-advisor-assignments.show');
Route::delete('/academic/academic-advisor-assignments/{academicAdvisorAssignment}', [AcademicAdvisorAssignmentController::class, 'destroy'])
    ->middleware('active_permission:academic-advisor-assignment.delete')
    ->name('academic.academic-advisor-assignments.destroy');

// CRUD Kurikulum (Inertia React) — paritas Livewire + duplikat.
Route::get('/academic/curriculums/export', [CurriculumController::class, 'export'])
    ->middleware('active_permission:curriculum.viewAny')
    ->name('academic.curriculums.export');
Route::get('/academic/curriculums/import/template', [CurriculumController::class, 'importTemplate'])
    ->middleware('active_permission:curriculum.create')
    ->name('academic.curriculums.import-template');
Route::post('/academic/curriculums/import', [CurriculumController::class, 'import'])
    ->middleware('active_permission:curriculum.create')
    ->name('academic.curriculums.import');
Route::get('/academic/curriculums/search-courses', [CurriculumController::class, 'searchCourses'])
    ->middleware('active_permission:curriculum.viewAny')
    ->name('academic.curriculums.search-courses');
Route::post('/academic/curriculums/bulk-destroy', [CurriculumController::class, 'bulkDestroy'])
    ->middleware('active_permission:curriculum.delete')
    ->name('academic.curriculums.bulk-destroy');
Route::post('/academic/curriculums/bulk-restore', [CurriculumController::class, 'bulkRestore'])
    ->middleware('active_permission:curriculum.update|curriculum.delete')
    ->name('academic.curriculums.bulk-restore');
Route::post('/academic/curriculums/bulk-force-destroy', [CurriculumController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:curriculum.update|curriculum.delete')
    ->name('academic.curriculums.bulk-force-destroy');
Route::post('/academic/curriculums/{id}/restore', [CurriculumController::class, 'restore'])
    ->middleware('active_permission:curriculum.update|curriculum.delete')
    ->name('academic.curriculums.restore');
Route::delete('/academic/curriculums/{id}/force', [CurriculumController::class, 'forceDestroy'])
    ->middleware('active_permission:curriculum.update|curriculum.delete')
    ->name('academic.curriculums.force-destroy');
Route::post('/academic/curriculums/{curriculum}/toggle', [CurriculumController::class, 'toggle'])
    ->middleware('active_permission:curriculum.update')
    ->name('academic.curriculums.toggle');
Route::post('/academic/curriculums/{curriculum}/duplicate', [CurriculumController::class, 'duplicate'])
    ->middleware('active_permission:curriculum.create')
    ->name('academic.curriculums.duplicate');
Route::post('/academic/curriculums/{curriculum}/courses', [CurriculumController::class, 'courseStore'])
    ->middleware('active_permission:curriculum.update')
    ->name('academic.curriculums.courses.store');
Route::post('/academic/curriculums/{curriculum}/courses/bulk-destroy', [CurriculumController::class, 'courseBulkDestroy'])
    ->middleware('active_permission:curriculum.update')
    ->name('academic.curriculums.courses.bulk-destroy');
Route::put('/academic/curriculums/{curriculum}/courses/{row}', [CurriculumController::class, 'courseUpdate'])
    ->middleware('active_permission:curriculum.update')
    ->name('academic.curriculums.courses.update');
Route::delete('/academic/curriculums/{curriculum}/courses/{row}', [CurriculumController::class, 'courseDestroy'])
    ->middleware('active_permission:curriculum.update')
    ->name('academic.curriculums.courses.destroy');
Route::get('/academic/curriculums', [CurriculumController::class, 'index'])
    ->middleware('active_permission:curriculum.viewAny')
    ->name('academic.curriculums.index');
Route::get('/academic/curriculums/create', [CurriculumController::class, 'create'])
    ->middleware('active_permission:curriculum.create')
    ->name('academic.curriculums.create');
Route::post('/academic/curriculums', [CurriculumController::class, 'store'])
    ->middleware('active_permission:curriculum.create')
    ->name('academic.curriculums.store');
Route::get('/academic/curriculums/{curriculum}/edit', [CurriculumController::class, 'edit'])
    ->middleware('active_permission:curriculum.update')
    ->name('academic.curriculums.edit');
Route::put('/academic/curriculums/{curriculum}', [CurriculumController::class, 'update'])
    ->middleware('active_permission:curriculum.update')
    ->name('academic.curriculums.update');
Route::get('/academic/curriculums/{curriculum}', [CurriculumController::class, 'show'])
    ->middleware('active_permission:curriculum.view')
    ->name('academic.curriculums.show');
Route::delete('/academic/curriculums/{curriculum}', [CurriculumController::class, 'destroy'])
    ->middleware('active_permission:curriculum.delete')
    ->name('academic.curriculums.destroy');

// CRUD Penawaran Kelas (Inertia React, workspace) — paritas Livewire.
Route::get('/academic/course-offerings/export', [CourseOfferingController::class, 'export'])
    ->middleware('active_permission:course-offering.viewAny')
    ->name('academic.course-offerings.export');
Route::get('/academic/course-offerings/import/template', [CourseOfferingController::class, 'importTemplate'])
    ->middleware('active_permission:course-offering.create')
    ->name('academic.course-offerings.import-template');
Route::post('/academic/course-offerings/import', [CourseOfferingController::class, 'import'])
    ->middleware('active_permission:course-offering.create')
    ->name('academic.course-offerings.import');
Route::post('/academic/course-offerings/bulk-destroy', [CourseOfferingController::class, 'bulkDestroy'])
    ->middleware('active_permission:course-offering.delete')
    ->name('academic.course-offerings.bulk-destroy');
Route::post('/academic/course-offerings/bulk-restore', [CourseOfferingController::class, 'bulkRestore'])
    ->middleware('active_permission:course-offering.update|course-offering.delete')
    ->name('academic.course-offerings.bulk-restore');
Route::post('/academic/course-offerings/bulk-force-destroy', [CourseOfferingController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:course-offering.update|course-offering.delete')
    ->name('academic.course-offerings.bulk-force-destroy');
Route::post('/academic/course-offerings/{id}/restore', [CourseOfferingController::class, 'restore'])
    ->middleware('active_permission:course-offering.update|course-offering.delete')
    ->name('academic.course-offerings.restore');
Route::delete('/academic/course-offerings/{id}/force', [CourseOfferingController::class, 'forceDestroy'])
    ->middleware('active_permission:course-offering.update|course-offering.delete')
    ->name('academic.course-offerings.force-destroy');
Route::post('/academic/course-offerings/{courseOffering}/generate', [CourseOfferingController::class, 'generate'])
    ->middleware('active_permission:course-offering.update')
    ->name('academic.course-offerings.generate');
Route::get('/academic/course-offerings/search-lecturers', [CourseOfferingController::class, 'searchLecturers'])
    ->middleware('active_permission:course-offering.viewAny')
    ->name('academic.course-offerings.search-lecturers');
Route::post('/academic/course-offerings/{courseOffering}/lecturers', [CourseOfferingController::class, 'lecturerStore'])
    ->middleware('active_permission:course-offering.update')
    ->name('academic.course-offerings.lecturers.store');
Route::put('/academic/course-offerings/{courseOffering}/lecturers/{lecturer}', [CourseOfferingController::class, 'lecturerUpdate'])
    ->middleware('active_permission:course-offering.update')
    ->name('academic.course-offerings.lecturers.update');
Route::delete('/academic/course-offerings/{courseOffering}/lecturers/{lecturer}', [CourseOfferingController::class, 'lecturerDestroy'])
    ->middleware('active_permission:course-offering.update')
    ->name('academic.course-offerings.lecturers.destroy');
Route::get('/academic/course-offerings', [CourseOfferingController::class, 'index'])
    ->middleware('active_permission:course-offering.viewAny')
    ->name('academic.course-offerings.index');
Route::get('/academic/course-offerings/create', [CourseOfferingController::class, 'create'])
    ->middleware('active_permission:course-offering.create')
    ->name('academic.course-offerings.create');
Route::post('/academic/course-offerings', [CourseOfferingController::class, 'store'])
    ->middleware('active_permission:course-offering.create')
    ->name('academic.course-offerings.store');
Route::get('/academic/course-offerings/{courseOffering}/edit', [CourseOfferingController::class, 'edit'])
    ->middleware('active_permission:course-offering.update')
    ->name('academic.course-offerings.edit');
Route::put('/academic/course-offerings/{courseOffering}', [CourseOfferingController::class, 'update'])
    ->middleware('active_permission:course-offering.update')
    ->name('academic.course-offerings.update');
Route::get('/academic/course-offerings/{courseOffering}', [CourseOfferingController::class, 'show'])
    ->middleware('active_permission:course-offering.view')
    ->name('academic.course-offerings.show');
Route::delete('/academic/course-offerings/{courseOffering}', [CourseOfferingController::class, 'destroy'])
    ->middleware('active_permission:course-offering.delete')
    ->name('academic.course-offerings.destroy');

// CRUD Jadwal Kuliah (Inertia React) — paritas Livewire + cegah bentrok.
Route::get('/academic/course-schedules/export', [CourseScheduleController::class, 'export'])
    ->middleware('active_permission:course-schedule.viewAny')
    ->name('academic.course-schedules.export');
Route::get('/academic/course-schedules/import/template', [CourseScheduleController::class, 'importTemplate'])
    ->middleware('active_permission:course-schedule.create')
    ->name('academic.course-schedules.import-template');
Route::post('/academic/course-schedules/import', [CourseScheduleController::class, 'import'])
    ->middleware('active_permission:course-schedule.create')
    ->name('academic.course-schedules.import');
Route::get('/academic/course-schedules/search-offerings', [CourseScheduleController::class, 'searchOfferings'])
    ->middleware('active_permission:course-schedule.viewAny')
    ->name('academic.course-schedules.search-offerings');
Route::get('/academic/course-schedules/offering-lecturers/{courseOffering}', [CourseScheduleController::class, 'offeringLecturers'])
    ->middleware('active_permission:course-schedule.viewAny')
    ->name('academic.course-schedules.offering-lecturers');
Route::post('/academic/course-schedules/bulk-destroy', [CourseScheduleController::class, 'bulkDestroy'])
    ->middleware('active_permission:course-schedule.delete')
    ->name('academic.course-schedules.bulk-destroy');
Route::post('/academic/course-schedules/bulk-restore', [CourseScheduleController::class, 'bulkRestore'])
    ->middleware('active_permission:course-schedule.update|course-schedule.delete')
    ->name('academic.course-schedules.bulk-restore');
Route::post('/academic/course-schedules/bulk-force-destroy', [CourseScheduleController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:course-schedule.update|course-schedule.delete')
    ->name('academic.course-schedules.bulk-force-destroy');
Route::post('/academic/course-schedules/{id}/restore', [CourseScheduleController::class, 'restore'])
    ->middleware('active_permission:course-schedule.update|course-schedule.delete')
    ->name('academic.course-schedules.restore');
Route::delete('/academic/course-schedules/{id}/force', [CourseScheduleController::class, 'forceDestroy'])
    ->middleware('active_permission:course-schedule.update|course-schedule.delete')
    ->name('academic.course-schedules.force-destroy');
Route::post('/academic/course-schedules/{courseSchedule}/toggle', [CourseScheduleController::class, 'toggle'])
    ->middleware('active_permission:course-schedule.update')
    ->name('academic.course-schedules.toggle');
Route::get('/academic/course-schedules', [CourseScheduleController::class, 'index'])
    ->middleware('active_permission:course-schedule.viewAny')
    ->name('academic.course-schedules.index');
Route::get('/academic/course-schedules/create', [CourseScheduleController::class, 'create'])
    ->middleware('active_permission:course-schedule.create')
    ->name('academic.course-schedules.create');
Route::post('/academic/course-schedules', [CourseScheduleController::class, 'store'])
    ->middleware('active_permission:course-schedule.create')
    ->name('academic.course-schedules.store');
Route::get('/academic/course-schedules/{courseSchedule}/edit', [CourseScheduleController::class, 'edit'])
    ->middleware('active_permission:course-schedule.update')
    ->name('academic.course-schedules.edit');
Route::put('/academic/course-schedules/{courseSchedule}', [CourseScheduleController::class, 'update'])
    ->middleware('active_permission:course-schedule.update')
    ->name('academic.course-schedules.update');
Route::get('/academic/course-schedules/{courseSchedule}', [CourseScheduleController::class, 'show'])
    ->middleware('active_permission:course-schedule.view')
    ->name('academic.course-schedules.show');
Route::delete('/academic/course-schedules/{courseSchedule}', [CourseScheduleController::class, 'destroy'])
    ->middleware('active_permission:course-schedule.delete')
    ->name('academic.course-schedules.destroy');

// Sesi absensi (nested di offering, gate course-offering.view — paritas).
Route::get('/academic/course-offerings/{offeringId}/attendance-sessions/{id}', [AttendanceSessionController::class, 'show'])
    ->middleware('active_permission:course-offering.view')
    ->name('academic.attendance-sessions.show');
Route::put('/academic/course-offerings/{offeringId}/attendance-sessions/{id}', [AttendanceSessionController::class, 'update'])
    ->middleware('active_permission:course-offering.update')
    ->name('academic.attendance-sessions.update');
Route::post('/academic/course-offerings/{offeringId}/attendance-sessions/{id}/records', [AttendanceSessionController::class, 'saveRecords'])
    ->middleware('active_permission:course-offering.update')
    ->name('academic.attendance-sessions.save-records');

// Log Notifikasi (Inertia React, read-only) — paritas Livewire.
Route::get('/system/notification-logs/export', [NotificationLogController::class, 'export'])
    ->middleware('active_permission:notification-log.viewAny')
    ->name('system.notification-logs.export');
Route::get('/system/notification-logs', [NotificationLogController::class, 'index'])
    ->middleware('active_permission:notification-log.viewAny')
    ->name('system.notification-logs.index');

// Log Aktivitas (Inertia React, read-only + detail) — paritas Livewire.
Route::get('/system/activity-logs', [ActivityLogController::class, 'index'])
    ->middleware('active_permission:activity-log.viewAny')
    ->name('system.activity-logs.index');
Route::get('/system/activity-logs/{id}', [ActivityLogController::class, 'show'])
    ->middleware('active_permission:activity-log.view')
    ->name('system.activity-logs.show');
Route::get('/access/permissions', [PermissionController::class, 'index'])
    ->middleware('active_permission:permission.viewAny')
    ->name('access.permissions.index');
Route::get('/access/permissions/create', [PermissionController::class, 'create'])
    ->middleware('active_permission:permission.create')
    ->name('access.permissions.create');
Route::post('/access/permissions', [PermissionController::class, 'store'])
    ->middleware('active_permission:permission.create')
    ->name('access.permissions.store');
Route::get('/access/permissions/{permission}/edit', [PermissionController::class, 'edit'])
    ->middleware('active_permission:permission.update')
    ->name('access.permissions.edit');
Route::put('/access/permissions/{permission}', [PermissionController::class, 'update'])
    ->middleware('active_permission:permission.update')
    ->name('access.permissions.update');
Route::delete('/access/permissions/{permission}', [PermissionController::class, 'destroy'])
    ->middleware('active_permission:permission.delete')
    ->name('access.permissions.destroy');

Route::livewire('/financial/dashboard', 'admin.financial.dashboard.index')
    ->middleware('active_permission:financial-dashboard.viewAny')
    ->name('financial.dashboard.index');
Route::livewire('/student-services/dashboard', 'admin.student-services.dashboard.index')
    ->middleware('active_permission:student-service-dashboard.viewAny')
    ->name('student-services.dashboard.index');
Route::get('/academic/{resource}/export/pdf', [AdminAcademicExportController::class, 'pdf'])
    ->name('academic.exports.pdf');
Route::get('/academic/{resource}/imports/template', [AdminAcademicExportController::class, 'importTemplate'])
    ->name('academic.import-template');

Route::get('/admission/documents/{document}/preview', [AdmissionDocumentController::class, 'adminPreview'])
    ->middleware('active_permission:admission-application.view')
    ->name('admission.documents.preview');
Route::get('/organization/user-development-attachments/{attachment}/preview', [UserDevelopmentAttachmentController::class, 'adminPreview'])
    ->middleware('active_permission:user-development-record.view')
    ->name('organization.user-development-records.attachments.preview');
Route::get('/organization/tridharma-attachments/{attachment}/preview', [TridharmaAttachmentController::class, 'adminPreview'])
    ->middleware('active_permission:tridharma-record.view')
    ->name('organization.tridharma-records.attachments.preview');
Route::get('/organization/lecturer-workload-submissions/export/{format}', [LecturerWorkloadExportController::class, 'admin'])
    ->whereIn('format', ['csv', 'xlsx', 'pdf'])
    ->name('organization.lecturer-workload-submissions.export');
Route::get('/admission/applications/{application}/acceptance-letter', [AcceptanceLetterController::class, 'show'])
    ->middleware('active_permission:admission-application.view')
    ->name('admission.applications.acceptance-letter');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

Route::get('/financial/payments/{payment}/receipt', [PaymentReceiptController::class, 'admin'])
    ->middleware('active_permission:payment.view')
    ->name('financial.payments.receipt');
foreach (['csv', 'xlsx', 'pdf'] as $format) {
    Route::get("/financial/reports/export/{$format}", [FinancialReportExportController::class, $format])
        ->middleware('active_permission:financial-report.viewAny')
        ->name("financial.reports.export.{$format}");
}

Route::get('/student-services/letter-requests/{request}/download', [ServiceLetterDownloadController::class, 'admin'])
    ->middleware('active_permission:service-letter-request.view')
    ->name('student-services.letter-requests.download');
Route::get('/student-services/graduation-documents/{document}/preview', [GraduationDocumentController::class, 'adminPreview'])
    ->middleware('active_permission:graduation-application.view')
    ->name('student-services.graduation-documents.preview');
Route::get('/student-services/complaint-attachments/{attachment}/download', [ComplaintAttachmentController::class, 'admin'])
    ->middleware('active_permission:student-complaint.view')
    ->name('student-services.complaint-attachments.download');

Route::livewire('/alumni/profiles', 'admin.alumni.alumni-profiles.index')
    ->middleware('active_permission:alumni-profile.viewAny')->name('alumni.profiles.index');
Route::livewire('/alumni/profiles/create', 'admin.alumni.alumni-profiles.create')
    ->middleware('active_permission:alumni-profile.create')->name('alumni.profiles.create');
Route::livewire('/alumni/profiles/{id}/edit', 'admin.alumni.alumni-profiles.edit')
    ->middleware('active_permission:alumni-profile.update')->name('alumni.profiles.edit');
Route::livewire('/alumni/profiles/{id}', 'admin.alumni.alumni-profiles.show')
    ->middleware('active_permission:alumni-profile.view')->name('alumni.profiles.show');
Route::post('/alumni/profiles/batch-convert', [AlumniConversionController::class, 'batchConvert'])
    ->middleware('active_permission:alumni-profile.create')->name('alumni.profiles.batch-convert');

Route::livewire('/alumni/employer-partners', 'admin.alumni.employer-partners.index')
    ->middleware('active_permission:employer-partner.viewAny')->name('alumni.employer-partners.index');
Route::livewire('/alumni/employer-partners/create', 'admin.alumni.employer-partners.create')
    ->middleware('active_permission:employer-partner.create')->name('alumni.employer-partners.create');
Route::livewire('/alumni/employer-partners/{id}/edit', 'admin.alumni.employer-partners.edit')
    ->middleware('active_permission:employer-partner.update')->name('alumni.employer-partners.edit');

Route::livewire('/alumni/job-postings', 'admin.alumni.job-postings.index')
    ->middleware('active_permission:job-posting.viewAny')->name('alumni.job-postings.index');
Route::livewire('/alumni/job-postings/create', 'admin.alumni.job-postings.create')
    ->middleware('active_permission:job-posting.create')->name('alumni.job-postings.create');
Route::livewire('/alumni/job-postings/{id}/edit', 'admin.alumni.job-postings.edit')
    ->middleware('active_permission:job-posting.update')->name('alumni.job-postings.edit');
Route::livewire('/alumni/job-postings/{id}', 'admin.alumni.job-postings.show')
    ->middleware('active_permission:job-posting.view')->name('alumni.job-postings.show');

Route::livewire('/alumni/events', 'admin.alumni.alumni-events.index')
    ->middleware('active_permission:alumni-event.viewAny')->name('alumni.events.index');
Route::livewire('/alumni/events/create', 'admin.alumni.alumni-events.create')
    ->middleware('active_permission:alumni-event.create')->name('alumni.events.create');
Route::livewire('/alumni/events/{id}/edit', 'admin.alumni.alumni-events.edit')
    ->middleware('active_permission:alumni-event.update')->name('alumni.events.edit');
Route::livewire('/alumni/events/{id}', 'admin.alumni.alumni-events.show')
    ->middleware('active_permission:alumni-event.view')->name('alumni.events.show');

Route::livewire('/alumni/tracer-study', 'admin.alumni.tracer-study-campaigns.index')
    ->middleware('active_permission:tracer-study-campaign.viewAny')->name('alumni.tracer-study.index');
Route::livewire('/alumni/tracer-study/create', 'admin.alumni.tracer-study-campaigns.create')
    ->middleware('active_permission:tracer-study-campaign.create')->name('alumni.tracer-study.create');
Route::livewire('/alumni/tracer-study/{id}/edit', 'admin.alumni.tracer-study-campaigns.edit')
    ->middleware('active_permission:tracer-study-campaign.update')->name('alumni.tracer-study.edit');
Route::livewire('/alumni/tracer-study/{id}', 'admin.alumni.tracer-study-campaigns.show')
    ->middleware('active_permission:tracer-study-campaign.view')->name('alumni.tracer-study.show');
Route::livewire('/alumni/tracer-study/{id}/responses', 'admin.alumni.tracer-study-responses.index')
    ->middleware('active_permission:tracer-study-response.viewAny')->name('alumni.tracer-study.responses');
Route::livewire('/alumni/tracer-study-responses/{id}', 'admin.alumni.tracer-study-responses.show')
    ->middleware('active_permission:tracer-study-response.view')->name('alumni.tracer-study-responses.show');
Route::get('/alumni/tracer-study/{id}/analytics', [TracerStudyAnalyticsController::class, 'show'])
    ->middleware('active_permission:tracer-study-campaign.view')->name('alumni.tracer-study.analytics');
Route::get('/alumni/tracer-study/{id}/export/{format}', [TracerStudyAnalyticsController::class, 'export'])
    ->middleware('active_permission:tracer-study-campaign.view')
    ->whereIn('format', ['csv', 'xlsx', 'pdf'])
    ->name('alumni.tracer-study.export');
