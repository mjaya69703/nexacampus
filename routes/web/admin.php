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
use App\Http\Controllers\Admin\Academic\StudentGradeController;
use App\Http\Controllers\Admin\Academic\TranscriptController;
use App\Http\Controllers\Admin\Financial\ClearancePolicyController;
use App\Http\Controllers\Admin\Financial\InvoiceScheduleController;
use App\Http\Controllers\Admin\Financial\FinancialHoldController;
use App\Http\Controllers\Admin\Financial\AdminFinancialExportController;
use App\Http\Controllers\Admin\Financial\FinancialDashboardController;
use App\Http\Controllers\Admin\Financial\FinancialReportController;
use App\Http\Controllers\Admin\Financial\InstallmentRequestController;
use App\Http\Controllers\Admin\Financial\StudentCreditController;
use App\Http\Controllers\Admin\Financial\StudentScholarshipController;
use App\Http\Controllers\Admin\Financial\PaymentController;
use App\Http\Controllers\Admin\Financial\InvoiceAdjustmentController;
use App\Http\Controllers\Admin\Financial\StudentInvoiceController;
use App\Http\Controllers\Admin\Financial\ScholarshipController;
use App\Http\Controllers\Admin\Financial\TuitionFeeController;
use App\Http\Controllers\Admin\Academic\StudentRegistrationController;
use App\Http\Controllers\Admin\Academic\StudyPlanController;
use App\Http\Controllers\Admin\Academic\FacultyController;
use App\Http\Controllers\Admin\Academic\StudyProgramController;
use App\Http\Controllers\Admin\Publication\AdminPublicationExportController;
use App\Http\Controllers\Admin\Publication\FaqController;
use App\Http\Controllers\Admin\Publication\PublicationCategoryController;
use App\Http\Controllers\Admin\Publication\AnnouncementController;
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
    if (in_array($resource['plural'], ['permissions', 'roles', 'users', 'menus', 'settings', 'faculties', 'study-programs', 'courses', 'academic-years', 'academic-periods', 'academic-advisor-assignments', 'curriculums', 'course-offerings', 'course-schedules', 'student-registrations', 'study-plans', 'student-grades', 'transcripts', 'tuition-fees', 'clearance-policies', 'scholarships', 'invoice-schedules', 'student-invoices', 'payments', 'invoice-adjustments', 'installment-requests', 'student-scholarships', 'student-credits', 'financial-holds', 'financial-reports', 'notification-logs', 'activity-logs']) && in_array($resource['area'], ['access', 'system', 'academic', 'financial'])) {
        continue;
    }

    // Batch-1 publikasi: categories & faqs → Inertia React.
    if (in_array($resource['plural'], ['categories', 'faqs', 'announcements']) && ($resource['area'] ?? null) === 'publication') {
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

// CRUD Kategori Publikasi (Inertia React) — Batch-1, nama route & middleware paritas Livewire.
Route::get('/publication/categories/export', [PublicationCategoryController::class, 'export'])
    ->middleware('active_permission:publication-category.viewAny')
    ->name('publication.categories.export');
Route::get('/publication/categories/import/template', [PublicationCategoryController::class, 'importTemplate'])
    ->middleware('active_permission:publication-category.create')
    ->name('publication.categories.import-template');
Route::post('/publication/categories/import', [PublicationCategoryController::class, 'import'])
    ->middleware('active_permission:publication-category.create')
    ->name('publication.categories.import');
Route::post('/publication/categories/bulk-destroy', [PublicationCategoryController::class, 'bulkDestroy'])
    ->middleware('active_permission:publication-category.delete')
    ->name('publication.categories.bulk-destroy');
Route::post('/publication/categories/bulk-restore', [PublicationCategoryController::class, 'bulkRestore'])
    ->middleware('active_permission:publication-category.update|publication-category.delete')
    ->name('publication.categories.bulk-restore');
Route::post('/publication/categories/bulk-force-destroy', [PublicationCategoryController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:publication-category.update|publication-category.delete')
    ->name('publication.categories.bulk-force-destroy');
Route::post('/publication/categories/{id}/restore', [PublicationCategoryController::class, 'restore'])
    ->middleware('active_permission:publication-category.update|publication-category.delete')
    ->name('publication.categories.restore');
Route::delete('/publication/categories/{id}/force', [PublicationCategoryController::class, 'forceDestroy'])
    ->middleware('active_permission:publication-category.update|publication-category.delete')
    ->name('publication.categories.force-destroy');
Route::post('/publication/categories/{category}/toggle', [PublicationCategoryController::class, 'toggle'])
    ->middleware('active_permission:publication-category.update')
    ->name('publication.categories.toggle');
Route::get('/publication/categories', [PublicationCategoryController::class, 'index'])
    ->middleware('active_permission:publication-category.viewAny')
    ->name('publication.categories.index');
Route::get('/publication/categories/create', [PublicationCategoryController::class, 'create'])
    ->middleware('active_permission:publication-category.create')
    ->name('publication.categories.create');
Route::post('/publication/categories', [PublicationCategoryController::class, 'store'])
    ->middleware('active_permission:publication-category.create')
    ->name('publication.categories.store');
Route::get('/publication/categories/{category}/edit', [PublicationCategoryController::class, 'edit'])
    ->middleware('active_permission:publication-category.update')
    ->name('publication.categories.edit');
Route::put('/publication/categories/{category}', [PublicationCategoryController::class, 'update'])
    ->middleware('active_permission:publication-category.update')
    ->name('publication.categories.update');
Route::delete('/publication/categories/{category}', [PublicationCategoryController::class, 'destroy'])
    ->middleware('active_permission:publication-category.delete')
    ->name('publication.categories.destroy');

// CRUD FAQ (Inertia React) — Batch-1, nama route & middleware paritas Livewire.
Route::get('/publication/faqs/export', [FaqController::class, 'export'])
    ->middleware('active_permission:faq.viewAny')
    ->name('publication.faqs.export');
Route::get('/publication/faqs/import/template', [FaqController::class, 'importTemplate'])
    ->middleware('active_permission:faq.create')
    ->name('publication.faqs.import-template');
Route::post('/publication/faqs/import', [FaqController::class, 'import'])
    ->middleware('active_permission:faq.create')
    ->name('publication.faqs.import');
Route::post('/publication/faqs/bulk-destroy', [FaqController::class, 'bulkDestroy'])
    ->middleware('active_permission:faq.delete')
    ->name('publication.faqs.bulk-destroy');
Route::post('/publication/faqs/bulk-restore', [FaqController::class, 'bulkRestore'])
    ->middleware('active_permission:faq.update|faq.delete')
    ->name('publication.faqs.bulk-restore');
Route::post('/publication/faqs/bulk-force-destroy', [FaqController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:faq.update|faq.delete')
    ->name('publication.faqs.bulk-force-destroy');
Route::post('/publication/faqs/{id}/restore', [FaqController::class, 'restore'])
    ->middleware('active_permission:faq.update|faq.delete')
    ->name('publication.faqs.restore');
Route::delete('/publication/faqs/{id}/force', [FaqController::class, 'forceDestroy'])
    ->middleware('active_permission:faq.update|faq.delete')
    ->name('publication.faqs.force-destroy');
Route::post('/publication/faqs/{faq}/toggle', [FaqController::class, 'toggle'])
    ->middleware('active_permission:faq.update')
    ->name('publication.faqs.toggle');
Route::get('/publication/faqs', [FaqController::class, 'index'])
    ->middleware('active_permission:faq.viewAny')
    ->name('publication.faqs.index');
Route::get('/publication/faqs/create', [FaqController::class, 'create'])
    ->middleware('active_permission:faq.create')
    ->name('publication.faqs.create');
Route::post('/publication/faqs', [FaqController::class, 'store'])
    ->middleware('active_permission:faq.create')
    ->name('publication.faqs.store');
Route::get('/publication/faqs/{faq}/edit', [FaqController::class, 'edit'])
    ->middleware('active_permission:faq.update')
    ->name('publication.faqs.edit');
Route::put('/publication/faqs/{faq}', [FaqController::class, 'update'])
    ->middleware('active_permission:faq.update')
    ->name('publication.faqs.update');
Route::delete('/publication/faqs/{faq}', [FaqController::class, 'destroy'])
    ->middleware('active_permission:faq.delete')
    ->name('publication.faqs.destroy');

// CRUD Pengumuman (Inertia React) — Batch-2, nama route & middleware paritas Livewire + publish.
Route::get('/publication/announcements/export', [AnnouncementController::class, 'export'])
    ->middleware('active_permission:announcement.viewAny')
    ->name('publication.announcements.export');
Route::get('/publication/announcements/import/template', [AnnouncementController::class, 'importTemplate'])
    ->middleware('active_permission:announcement.create')
    ->name('publication.announcements.import-template');
Route::post('/publication/announcements/import', [AnnouncementController::class, 'import'])
    ->middleware('active_permission:announcement.create')
    ->name('publication.announcements.import');
Route::post('/publication/announcements/bulk-destroy', [AnnouncementController::class, 'bulkDestroy'])
    ->middleware('active_permission:announcement.delete')
    ->name('publication.announcements.bulk-destroy');
Route::post('/publication/announcements/bulk-restore', [AnnouncementController::class, 'bulkRestore'])
    ->middleware('active_permission:announcement.update|announcement.delete')
    ->name('publication.announcements.bulk-restore');
Route::post('/publication/announcements/bulk-force-destroy', [AnnouncementController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:announcement.update|announcement.delete')
    ->name('publication.announcements.bulk-force-destroy');
Route::post('/publication/announcements/bulk-publish', [AnnouncementController::class, 'bulkPublish'])
    ->middleware('active_permission:announcement.publish')
    ->name('publication.announcements.bulk-publish');
Route::post('/publication/announcements/{id}/restore', [AnnouncementController::class, 'restore'])
    ->middleware('active_permission:announcement.update|announcement.delete')
    ->name('publication.announcements.restore');
Route::delete('/publication/announcements/{id}/force', [AnnouncementController::class, 'forceDestroy'])
    ->middleware('active_permission:announcement.update|announcement.delete')
    ->name('publication.announcements.force-destroy');
Route::get('/publication/announcements/search-targets/{type}', [AnnouncementController::class, 'searchTargets'])
    ->middleware('active_permission:announcement.create|announcement.update')
    ->name('publication.announcements.search-targets');
Route::post('/publication/announcements/{announcement}/toggle-pin', [AnnouncementController::class, 'togglePin'])
    ->middleware('active_permission:announcement.update')
    ->name('publication.announcements.toggle-pin');
Route::post('/publication/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])
    ->middleware('active_permission:announcement.publish')
    ->name('publication.announcements.publish');
Route::post('/publication/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish'])
    ->middleware('active_permission:announcement.publish')
    ->name('publication.announcements.unpublish');
Route::get('/publication/announcements', [AnnouncementController::class, 'index'])
    ->middleware('active_permission:announcement.viewAny')
    ->name('publication.announcements.index');
Route::get('/publication/announcements/create', [AnnouncementController::class, 'create'])
    ->middleware('active_permission:announcement.create')
    ->name('publication.announcements.create');
Route::post('/publication/announcements', [AnnouncementController::class, 'store'])
    ->middleware('active_permission:announcement.create')
    ->name('publication.announcements.store');
Route::get('/publication/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])
    ->middleware('active_permission:announcement.update')
    ->name('publication.announcements.edit');
Route::get('/publication/announcements/{announcement}', [AnnouncementController::class, 'show'])
    ->middleware('active_permission:announcement.view')
    ->name('publication.announcements.show');
Route::put('/publication/announcements/{announcement}', [AnnouncementController::class, 'update'])
    ->middleware('active_permission:announcement.update')
    ->name('publication.announcements.update');
Route::delete('/publication/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
    ->middleware('active_permission:announcement.delete')
    ->name('publication.announcements.destroy');

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

// CRUD Registrasi Mahasiswa (Inertia React) — paritas Livewire + approval.
Route::get('/academic/student-registrations/export', [StudentRegistrationController::class, 'export'])
    ->middleware('active_permission:student-registration.viewAny')
    ->name('academic.student-registrations.export');
Route::get('/academic/student-registrations/search-students', [StudentRegistrationController::class, 'searchStudents'])
    ->middleware('active_permission:student-registration.viewAny')
    ->name('academic.student-registrations.search-students');
Route::post('/academic/student-registrations/bulk-destroy', [StudentRegistrationController::class, 'bulkDestroy'])
    ->middleware('active_permission:student-registration.delete')
    ->name('academic.student-registrations.bulk-destroy');
Route::post('/academic/student-registrations/bulk-restore', [StudentRegistrationController::class, 'bulkRestore'])
    ->middleware('active_permission:student-registration.update|student-registration.delete')
    ->name('academic.student-registrations.bulk-restore');
Route::post('/academic/student-registrations/bulk-force-destroy', [StudentRegistrationController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:student-registration.update|student-registration.delete')
    ->name('academic.student-registrations.bulk-force-destroy');
Route::post('/academic/student-registrations/{id}/restore', [StudentRegistrationController::class, 'restore'])
    ->middleware('active_permission:student-registration.update|student-registration.delete')
    ->name('academic.student-registrations.restore');
Route::delete('/academic/student-registrations/{id}/force', [StudentRegistrationController::class, 'forceDestroy'])
    ->middleware('active_permission:student-registration.update|student-registration.delete')
    ->name('academic.student-registrations.force-destroy');
Route::post('/academic/student-registrations/{studentRegistration}/toggle', [StudentRegistrationController::class, 'toggle'])
    ->middleware('active_permission:student-registration.update')
    ->name('academic.student-registrations.toggle');
Route::post('/academic/student-registrations/{studentRegistration}/approve', [StudentRegistrationController::class, 'approve'])
    ->middleware('active_permission:student-registration.update')
    ->name('academic.student-registrations.approve');
Route::get('/academic/student-registrations', [StudentRegistrationController::class, 'index'])
    ->middleware('active_permission:student-registration.viewAny')
    ->name('academic.student-registrations.index');
Route::get('/academic/student-registrations/create', [StudentRegistrationController::class, 'create'])
    ->middleware('active_permission:student-registration.create')
    ->name('academic.student-registrations.create');
Route::post('/academic/student-registrations', [StudentRegistrationController::class, 'store'])
    ->middleware('active_permission:student-registration.create')
    ->name('academic.student-registrations.store');
Route::get('/academic/student-registrations/{studentRegistration}/edit', [StudentRegistrationController::class, 'edit'])
    ->middleware('active_permission:student-registration.update')
    ->name('academic.student-registrations.edit');
Route::put('/academic/student-registrations/{studentRegistration}', [StudentRegistrationController::class, 'update'])
    ->middleware('active_permission:student-registration.update')
    ->name('academic.student-registrations.update');
Route::get('/academic/student-registrations/{studentRegistration}', [StudentRegistrationController::class, 'show'])
    ->middleware('active_permission:student-registration.view')
    ->name('academic.student-registrations.show');
Route::delete('/academic/student-registrations/{studentRegistration}', [StudentRegistrationController::class, 'destroy'])
    ->middleware('active_permission:student-registration.delete')
    ->name('academic.student-registrations.destroy');

// CRUD KRS (Inertia React, hapus permanen) — paritas Livewire.
Route::get('/academic/study-plans/export', [StudyPlanController::class, 'export'])
    ->middleware('active_permission:study-plan.viewAny')
    ->name('academic.study-plans.export');
Route::get('/academic/study-plans/search-students', [StudyPlanController::class, 'searchStudents'])
    ->middleware('active_permission:study-plan.viewAny')
    ->name('academic.study-plans.search-students');
Route::get('/academic/study-plans/registration-options', [StudyPlanController::class, 'registrationOptions'])
    ->middleware('active_permission:study-plan.viewAny')
    ->name('academic.study-plans.registration-options');
Route::get('/academic/study-plans/offering-options', [StudyPlanController::class, 'offeringOptions'])
    ->middleware('active_permission:study-plan.viewAny')
    ->name('academic.study-plans.offering-options');
Route::post('/academic/study-plans/bulk-destroy', [StudyPlanController::class, 'bulkDestroy'])
    ->middleware('active_permission:study-plan.delete')
    ->name('academic.study-plans.bulk-destroy');
Route::post('/academic/study-plans/{studyPlan}/details', [StudyPlanController::class, 'detailStore'])
    ->middleware('active_permission:study-plan.update')
    ->name('academic.study-plans.details.store');
Route::post('/academic/study-plans/{studyPlan}/details/bulk-destroy', [StudyPlanController::class, 'detailBulkDestroy'])
    ->middleware('active_permission:study-plan.update')
    ->name('academic.study-plans.details.bulk-destroy');
Route::put('/academic/study-plans/{studyPlan}/details/{detail}', [StudyPlanController::class, 'detailUpdate'])
    ->middleware('active_permission:study-plan.update')
    ->name('academic.study-plans.details.update');
Route::delete('/academic/study-plans/{studyPlan}/details/{detail}', [StudyPlanController::class, 'detailDestroy'])
    ->middleware('active_permission:study-plan.update')
    ->name('academic.study-plans.details.destroy');
Route::get('/academic/study-plans', [StudyPlanController::class, 'index'])
    ->middleware('active_permission:study-plan.viewAny')
    ->name('academic.study-plans.index');
Route::get('/academic/study-plans/create', [StudyPlanController::class, 'create'])
    ->middleware('active_permission:study-plan.create')
    ->name('academic.study-plans.create');
Route::post('/academic/study-plans', [StudyPlanController::class, 'store'])
    ->middleware('active_permission:study-plan.create')
    ->name('academic.study-plans.store');
Route::get('/academic/study-plans/{studyPlan}/edit', [StudyPlanController::class, 'edit'])
    ->middleware('active_permission:study-plan.update')
    ->name('academic.study-plans.edit');
Route::put('/academic/study-plans/{studyPlan}', [StudyPlanController::class, 'update'])
    ->middleware('active_permission:study-plan.update')
    ->name('academic.study-plans.update');
Route::get('/academic/study-plans/{studyPlan}', [StudyPlanController::class, 'show'])
    ->middleware('active_permission:study-plan.view')
    ->name('academic.study-plans.show');
Route::delete('/academic/study-plans/{studyPlan}', [StudyPlanController::class, 'destroy'])
    ->middleware('active_permission:study-plan.delete')
    ->name('academic.study-plans.destroy');

// CRUD nilai mahasiswa (Inertia React) — paritas Livewire + kunci Published.
Route::get('/academic/student-grades/export', [StudentGradeController::class, 'export'])
    ->middleware('active_permission:student-grade.viewAny')
    ->name('academic.student-grades.export');
Route::get('/academic/student-grades/search-details', [StudentGradeController::class, 'searchDetails'])
    ->middleware('active_permission:student-grade.viewAny')
    ->name('academic.student-grades.search-details');
Route::get('/academic/student-grades/grader-options', [StudentGradeController::class, 'graderOptions'])
    ->middleware('active_permission:student-grade.viewAny')
    ->name('academic.student-grades.grader-options');
Route::get('/academic/student-grades/offering-options', [StudentGradeController::class, 'offeringOptions'])
    ->middleware('active_permission:student-grade.viewAny')
    ->name('academic.student-grades.offering-options');
Route::post('/academic/student-grades/bulk-publish', [StudentGradeController::class, 'bulkPublish'])
    ->middleware('active_permission:student-grade.update')
    ->name('academic.student-grades.bulk-publish');
Route::post('/academic/student-grades/{studentGrade}/components', [StudentGradeController::class, 'componentStore'])
    ->middleware('active_permission:student-grade.update')
    ->name('academic.student-grades.components.store');
Route::put('/academic/student-grades/{studentGrade}/components/{component}', [StudentGradeController::class, 'componentUpdate'])
    ->middleware('active_permission:student-grade.update')
    ->name('academic.student-grades.components.update');
Route::delete('/academic/student-grades/{studentGrade}/components/{component}', [StudentGradeController::class, 'componentDestroy'])
    ->middleware('active_permission:student-grade.update')
    ->name('academic.student-grades.components.destroy');
Route::post('/academic/student-grades/{studentGrade}/finalize', [StudentGradeController::class, 'finalize'])
    ->middleware('active_permission:student-grade.update')
    ->name('academic.student-grades.finalize');
Route::post('/academic/student-grades/{studentGrade}/publish', [StudentGradeController::class, 'publish'])
    ->middleware('active_permission:student-grade.update')
    ->name('academic.student-grades.publish');
Route::post('/academic/student-grades/{studentGrade}/unpublish', [StudentGradeController::class, 'unpublish'])
    ->middleware('active_permission:student-grade.update')
    ->name('academic.student-grades.unpublish');
Route::get('/academic/student-grades', [StudentGradeController::class, 'index'])
    ->middleware('active_permission:student-grade.viewAny')
    ->name('academic.student-grades.index');
Route::get('/academic/student-grades/create', [StudentGradeController::class, 'create'])
    ->middleware('active_permission:student-grade.create')
    ->name('academic.student-grades.create');
Route::post('/academic/student-grades', [StudentGradeController::class, 'store'])
    ->middleware('active_permission:student-grade.create')
    ->name('academic.student-grades.store');
Route::get('/academic/student-grades/{studentGrade}/edit', [StudentGradeController::class, 'edit'])
    ->middleware('active_permission:student-grade.update')
    ->name('academic.student-grades.edit');
Route::put('/academic/student-grades/{studentGrade}', [StudentGradeController::class, 'update'])
    ->middleware('active_permission:student-grade.update')
    ->name('academic.student-grades.update');
Route::get('/academic/student-grades/{studentGrade}', [StudentGradeController::class, 'show'])
    ->middleware('active_permission:student-grade.view')
    ->name('academic.student-grades.show');
Route::delete('/academic/student-grades/{studentGrade}', [StudentGradeController::class, 'destroy'])
    ->middleware('active_permission:student-grade.delete')
    ->name('academic.student-grades.destroy');

// Transkrip (Inertia React, read-only + sync) — paritas Livewire.
Route::get('/academic/transcripts/export', [TranscriptController::class, 'export'])
    ->middleware('active_permission:transcript.viewAny')
    ->name('academic.transcripts.export');
Route::post('/academic/transcripts/bulk-sync', [TranscriptController::class, 'bulkSync'])
    ->middleware('active_permission:transcript.update')
    ->name('academic.transcripts.bulk-sync');
Route::get('/academic/transcripts', [TranscriptController::class, 'index'])
    ->middleware('active_permission:transcript.viewAny')
    ->name('academic.transcripts.index');
Route::get('/academic/transcripts/{transcript}', [TranscriptController::class, 'show'])
    ->middleware('active_permission:transcript.view')
    ->name('academic.transcripts.show');
Route::post('/academic/transcripts/{transcript}/sync', [TranscriptController::class, 'sync'])
    ->middleware('active_permission:transcript.update')
    ->name('academic.transcripts.sync');

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

Route::get('/financial/dashboard', [FinancialDashboardController::class, 'index'])
    ->middleware('active_permission:financial-dashboard.viewAny')
    ->name('financial.dashboard.index');

// PDF laporan tabel per resource financial (kop + tanda tangan, paritas akademik).
Route::get('/financial/{resource}/export/pdf', [AdminFinancialExportController::class, 'pdf'])
    ->whereIn('resource', ['tuition-fees', 'clearance-policies', 'scholarships', 'invoice-schedules', 'student-invoices', 'payments', 'invoice-adjustments', 'installment-requests', 'student-scholarships', 'student-credits', 'financial-holds'])
    ->name('financial.exports.pdf');
Route::get('/financial/financial-reports', [FinancialReportController::class, 'index'])
    ->middleware('active_permission:financial-report.viewAny')
    ->name('financial.financial-reports.index');
Route::livewire('/student-services/dashboard', 'admin.student-services.dashboard.index')
    ->middleware('active_permission:student-service-dashboard.viewAny')
    ->name('student-services.dashboard.index');
Route::get('/academic/{resource}/export/pdf', [AdminAcademicExportController::class, 'pdf'])
    ->name('academic.exports.pdf');
Route::get('/academic/{resource}/imports/template', [AdminAcademicExportController::class, 'importTemplate'])
    ->name('academic.import-template');
Route::get('/publication/{resource}/export/pdf', [AdminPublicationExportController::class, 'pdf'])
    ->name('publication.exports.pdf');
Route::get('/publication/{resource}/imports/template', [AdminPublicationExportController::class, 'importTemplate'])
    ->name('publication.import-template');

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

// CRUD Tarif Biaya Kuliah (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/financial/tuition-fees/export', [TuitionFeeController::class, 'export'])
    ->middleware('active_permission:tuition-fee.viewAny')
    ->name('financial.tuition-fees.export');
Route::post('/financial/tuition-fees/bulk-destroy', [TuitionFeeController::class, 'bulkDestroy'])
    ->middleware('active_permission:tuition-fee.delete')
    ->name('financial.tuition-fees.bulk-destroy');
Route::post('/financial/tuition-fees/bulk-restore', [TuitionFeeController::class, 'bulkRestore'])
    ->middleware('active_permission:tuition-fee.update|tuition-fee.delete')
    ->name('financial.tuition-fees.bulk-restore');
Route::post('/financial/tuition-fees/bulk-force-destroy', [TuitionFeeController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:tuition-fee.delete')
    ->name('financial.tuition-fees.bulk-force-destroy');
Route::post('/financial/tuition-fees/{id}/restore', [TuitionFeeController::class, 'restore'])
    ->middleware('active_permission:tuition-fee.update|tuition-fee.delete')
    ->name('financial.tuition-fees.restore');
Route::delete('/financial/tuition-fees/{id}/force', [TuitionFeeController::class, 'forceDestroy'])
    ->middleware('active_permission:tuition-fee.delete')
    ->name('financial.tuition-fees.force-destroy');
Route::get('/financial/tuition-fees/import/template', [TuitionFeeController::class, 'importTemplate'])
    ->middleware('active_permission:tuition-fee.create')
    ->name('financial.tuition-fees.import-template');
Route::post('/financial/tuition-fees/import', [TuitionFeeController::class, 'import'])
    ->middleware('active_permission:tuition-fee.create')
    ->name('financial.tuition-fees.import');
Route::get('/financial/tuition-fees', [TuitionFeeController::class, 'index'])
    ->middleware('active_permission:tuition-fee.viewAny')
    ->name('financial.tuition-fees.index');
Route::get('/financial/tuition-fees/create', [TuitionFeeController::class, 'create'])
    ->middleware('active_permission:tuition-fee.create')
    ->name('financial.tuition-fees.create');
Route::post('/financial/tuition-fees', [TuitionFeeController::class, 'store'])
    ->middleware('active_permission:tuition-fee.create')
    ->name('financial.tuition-fees.store');
Route::get('/financial/tuition-fees/{tuitionFee}/edit', [TuitionFeeController::class, 'edit'])
    ->middleware('active_permission:tuition-fee.update')
    ->name('financial.tuition-fees.edit');
Route::put('/financial/tuition-fees/{tuitionFee}', [TuitionFeeController::class, 'update'])
    ->middleware('active_permission:tuition-fee.update')
    ->name('financial.tuition-fees.update');
Route::post('/financial/tuition-fees/{tuitionFee}/toggle', [TuitionFeeController::class, 'toggle'])
    ->middleware('active_permission:tuition-fee.update')
    ->name('financial.tuition-fees.toggle');
Route::delete('/financial/tuition-fees/{tuitionFee}', [TuitionFeeController::class, 'destroy'])
    ->middleware('active_permission:tuition-fee.delete')
    ->name('financial.tuition-fees.destroy');

// CRUD Kebijakan Bebas Tanggungan (Inertia React) — paritas Livewire.
Route::get('/financial/clearance-policies/export', [ClearancePolicyController::class, 'export'])
    ->middleware('active_permission:clearance-policy.viewAny')
    ->name('financial.clearance-policies.export');
Route::post('/financial/clearance-policies/bulk-destroy', [ClearancePolicyController::class, 'bulkDestroy'])
    ->middleware('active_permission:clearance-policy.delete')
    ->name('financial.clearance-policies.bulk-destroy');
Route::get('/financial/clearance-policies/import/template', [ClearancePolicyController::class, 'importTemplate'])
    ->middleware('active_permission:clearance-policy.create')
    ->name('financial.clearance-policies.import-template');
Route::post('/financial/clearance-policies/import', [ClearancePolicyController::class, 'import'])
    ->middleware('active_permission:clearance-policy.create')
    ->name('financial.clearance-policies.import');
Route::get('/financial/clearance-policies', [ClearancePolicyController::class, 'index'])
    ->middleware('active_permission:clearance-policy.viewAny')
    ->name('financial.clearance-policies.index');
Route::get('/financial/clearance-policies/create', [ClearancePolicyController::class, 'create'])
    ->middleware('active_permission:clearance-policy.create')
    ->name('financial.clearance-policies.create');
Route::post('/financial/clearance-policies', [ClearancePolicyController::class, 'store'])
    ->middleware('active_permission:clearance-policy.create')
    ->name('financial.clearance-policies.store');
Route::get('/financial/clearance-policies/{clearancePolicy}/edit', [ClearancePolicyController::class, 'edit'])
    ->middleware('active_permission:clearance-policy.update')
    ->name('financial.clearance-policies.edit');
Route::put('/financial/clearance-policies/{clearancePolicy}', [ClearancePolicyController::class, 'update'])
    ->middleware('active_permission:clearance-policy.update')
    ->name('financial.clearance-policies.update');
Route::post('/financial/clearance-policies/{clearancePolicy}/toggle', [ClearancePolicyController::class, 'toggle'])
    ->middleware('active_permission:clearance-policy.update')
    ->name('financial.clearance-policies.toggle');
Route::delete('/financial/clearance-policies/{clearancePolicy}', [ClearancePolicyController::class, 'destroy'])
    ->middleware('active_permission:clearance-policy.delete')
    ->name('financial.clearance-policies.destroy');

// CRUD Program Beasiswa (Inertia React) — paritas Livewire.
Route::get('/financial/scholarships/export', [ScholarshipController::class, 'export'])
    ->middleware('active_permission:scholarship.viewAny')
    ->name('financial.scholarships.export');
Route::post('/financial/scholarships/bulk-destroy', [ScholarshipController::class, 'bulkDestroy'])
    ->middleware('active_permission:scholarship.delete')
    ->name('financial.scholarships.bulk-destroy');
Route::post('/financial/scholarships/bulk-restore', [ScholarshipController::class, 'bulkRestore'])
    ->middleware('active_permission:scholarship.update|scholarship.delete')
    ->name('financial.scholarships.bulk-restore');
Route::post('/financial/scholarships/bulk-force-destroy', [ScholarshipController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:scholarship.delete')
    ->name('financial.scholarships.bulk-force-destroy');
Route::get('/financial/scholarships/import/template', [ScholarshipController::class, 'importTemplate'])
    ->middleware('active_permission:scholarship.create')
    ->name('financial.scholarships.import-template');
Route::post('/financial/scholarships/import', [ScholarshipController::class, 'import'])
    ->middleware('active_permission:scholarship.create')
    ->name('financial.scholarships.import');
Route::post('/financial/scholarships/{id}/restore', [ScholarshipController::class, 'restore'])
    ->middleware('active_permission:scholarship.update|scholarship.delete')
    ->name('financial.scholarships.restore');
Route::delete('/financial/scholarships/{id}/force', [ScholarshipController::class, 'forceDestroy'])
    ->middleware('active_permission:scholarship.delete')
    ->name('financial.scholarships.force-destroy');
Route::get('/financial/scholarships', [ScholarshipController::class, 'index'])
    ->middleware('active_permission:scholarship.viewAny')
    ->name('financial.scholarships.index');
Route::get('/financial/scholarships/create', [ScholarshipController::class, 'create'])
    ->middleware('active_permission:scholarship.create')
    ->name('financial.scholarships.create');
Route::post('/financial/scholarships', [ScholarshipController::class, 'store'])
    ->middleware('active_permission:scholarship.create')
    ->name('financial.scholarships.store');
Route::get('/financial/scholarships/{scholarship}/edit', [ScholarshipController::class, 'edit'])
    ->middleware('active_permission:scholarship.update')
    ->name('financial.scholarships.edit');
Route::put('/financial/scholarships/{scholarship}', [ScholarshipController::class, 'update'])
    ->middleware('active_permission:scholarship.update')
    ->name('financial.scholarships.update');
Route::post('/financial/scholarships/{scholarship}/toggle', [ScholarshipController::class, 'toggle'])
    ->middleware('active_permission:scholarship.update')
    ->name('financial.scholarships.toggle');
Route::delete('/financial/scholarships/{scholarship}', [ScholarshipController::class, 'destroy'])
    ->middleware('active_permission:scholarship.delete')
    ->name('financial.scholarships.destroy');

// CRUD Jadwal Penerbitan Tagihan (Inertia React) — paritas Livewire.
Route::get('/financial/invoice-schedules/export', [InvoiceScheduleController::class, 'export'])
    ->middleware('active_permission:invoice-schedule.viewAny')
    ->name('financial.invoice-schedules.export');
Route::get('/financial/invoice-schedules/search-students', [InvoiceScheduleController::class, 'searchStudents'])
    ->middleware('active_permission:invoice-schedule.viewAny')
    ->name('financial.invoice-schedules.search-students');
Route::post('/financial/invoice-schedules/bulk-destroy', [InvoiceScheduleController::class, 'bulkDestroy'])
    ->middleware('active_permission:invoice-schedule.delete')
    ->name('financial.invoice-schedules.bulk-destroy');
Route::get('/financial/invoice-schedules', [InvoiceScheduleController::class, 'index'])
    ->middleware('active_permission:invoice-schedule.viewAny')
    ->name('financial.invoice-schedules.index');
Route::get('/financial/invoice-schedules/create', [InvoiceScheduleController::class, 'create'])
    ->middleware('active_permission:invoice-schedule.create')
    ->name('financial.invoice-schedules.create');
Route::post('/financial/invoice-schedules', [InvoiceScheduleController::class, 'store'])
    ->middleware('active_permission:invoice-schedule.create')
    ->name('financial.invoice-schedules.store');
Route::get('/financial/invoice-schedules/{invoiceSchedule}/edit', [InvoiceScheduleController::class, 'edit'])
    ->middleware('active_permission:invoice-schedule.update')
    ->name('financial.invoice-schedules.edit');
Route::put('/financial/invoice-schedules/{invoiceSchedule}', [InvoiceScheduleController::class, 'update'])
    ->middleware('active_permission:invoice-schedule.update')
    ->name('financial.invoice-schedules.update');
Route::post('/financial/invoice-schedules/{invoiceSchedule}/run', [InvoiceScheduleController::class, 'run'])
    ->middleware('active_permission:invoice-schedule.update')
    ->name('financial.invoice-schedules.run');
Route::delete('/financial/invoice-schedules/{invoiceSchedule}', [InvoiceScheduleController::class, 'destroy'])
    ->middleware('active_permission:invoice-schedule.delete')
    ->name('financial.invoice-schedules.destroy');

// CRUD Tagihan Mahasiswa (Inertia React) — paritas Livewire.
Route::get('/financial/student-invoices/export', [StudentInvoiceController::class, 'export'])
    ->middleware('active_permission:student-invoice.viewAny')
    ->name('financial.student-invoices.export');
Route::get('/financial/student-invoices/search-students', [StudentInvoiceController::class, 'searchStudents'])
    ->middleware('active_permission:student-invoice.viewAny')
    ->name('financial.student-invoices.search-students');
Route::post('/financial/student-invoices/bulk-cancel', [StudentInvoiceController::class, 'bulkCancel'])
    ->middleware('active_permission:student-invoice.delete')
    ->name('financial.student-invoices.bulk-cancel');
Route::get('/financial/student-invoices', [StudentInvoiceController::class, 'index'])
    ->middleware('active_permission:student-invoice.viewAny')
    ->name('financial.student-invoices.index');
Route::get('/financial/student-invoices/create', [StudentInvoiceController::class, 'create'])
    ->middleware('active_permission:student-invoice.create')
    ->name('financial.student-invoices.create');
Route::post('/financial/student-invoices', [StudentInvoiceController::class, 'store'])
    ->middleware('active_permission:student-invoice.create')
    ->name('financial.student-invoices.store');
Route::get('/financial/student-invoices/{studentInvoice}/edit', [StudentInvoiceController::class, 'edit'])
    ->middleware('active_permission:student-invoice.update')
    ->name('financial.student-invoices.edit');
Route::put('/financial/student-invoices/{studentInvoice}', [StudentInvoiceController::class, 'update'])
    ->middleware('active_permission:student-invoice.update')
    ->name('financial.student-invoices.update');
Route::get('/financial/student-invoices/{studentInvoice}', [StudentInvoiceController::class, 'show'])
    ->middleware('active_permission:student-invoice.view')
    ->name('financial.student-invoices.show');
Route::post('/financial/student-invoices/{studentInvoice}/issue', [StudentInvoiceController::class, 'issue'])
    ->middleware('active_permission:student-invoice.update')
    ->name('financial.student-invoices.issue');
Route::post('/financial/student-invoices/{studentInvoice}/cancel', [StudentInvoiceController::class, 'cancel'])
    ->middleware('active_permission:student-invoice.delete')
    ->name('financial.student-invoices.cancel');
Route::get('/financial/student-invoices/{studentInvoice}/pdf', [StudentInvoiceController::class, 'pdf'])
    ->middleware('active_permission:student-invoice.view')
    ->name('financial.student-invoices.pdf');
Route::post('/financial/student-invoices/{studentInvoice}/adjust', [StudentInvoiceController::class, 'adjust'])
    ->middleware('active_permission:student-invoice.update')
    ->name('financial.student-invoices.adjust');
Route::post('/financial/student-invoices/{studentInvoice}/apply-scholarship', [StudentInvoiceController::class, 'applyScholarship'])
    ->middleware('active_permission:student-invoice.update')
    ->name('financial.student-invoices.apply-scholarship');

// Pembayaran (Inertia React, read-only + verifikasi) — paritas Livewire.
Route::get('/financial/payments/export', [PaymentController::class, 'export'])
    ->middleware('active_permission:payment.viewAny')
    ->name('financial.payments.export');
Route::get('/financial/payments', [PaymentController::class, 'index'])
    ->middleware('active_permission:payment.viewAny')
    ->name('financial.payments.index');
Route::get('/financial/payments/{payment}', [PaymentController::class, 'show'])
    ->middleware('active_permission:payment.view')
    ->name('financial.payments.show');
Route::post('/financial/payments/{payment}/verify', [PaymentController::class, 'verify'])
    ->middleware('active_permission:payment.update')
    ->name('financial.payments.verify');
Route::post('/financial/payments/{payment}/reject', [PaymentController::class, 'reject'])
    ->middleware('active_permission:payment.update')
    ->name('financial.payments.reject');

// Log Penyesuaian Tagihan (Inertia React, read-only) — paritas Livewire.
Route::get('/financial/invoice-adjustments/export', [InvoiceAdjustmentController::class, 'export'])
    ->middleware('active_permission:invoice-adjustment.viewAny')
    ->name('financial.invoice-adjustments.export');
Route::get('/financial/invoice-adjustments', [InvoiceAdjustmentController::class, 'index'])
    ->middleware('active_permission:invoice-adjustment.viewAny')
    ->name('financial.invoice-adjustments.index');

// Pengajuan Cicilan (Inertia React, tinjau + putusan) — paritas Livewire.
Route::get('/financial/installment-requests/export', [InstallmentRequestController::class, 'export'])
    ->middleware('active_permission:installment-request.viewAny')
    ->name('financial.installment-requests.export');
Route::get('/financial/installment-requests', [InstallmentRequestController::class, 'index'])
    ->middleware('active_permission:installment-request.viewAny')
    ->name('financial.installment-requests.index');
Route::get('/financial/installment-requests/{installmentRequest}', [InstallmentRequestController::class, 'show'])
    ->middleware('active_permission:installment-request.view')
    ->name('financial.installment-requests.show');
Route::post('/financial/installment-requests/{installmentRequest}/approve', [InstallmentRequestController::class, 'approve'])
    ->middleware('active_permission:installment-request.update')
    ->name('financial.installment-requests.approve');
Route::post('/financial/installment-requests/{installmentRequest}/reject', [InstallmentRequestController::class, 'reject'])
    ->middleware('active_permission:installment-request.update')
    ->name('financial.installment-requests.reject');

// CRUD Penerima Beasiswa (Inertia React) — paritas Livewire.
Route::get('/financial/student-scholarships/export', [StudentScholarshipController::class, 'export'])
    ->middleware('active_permission:student-scholarship.viewAny')
    ->name('financial.student-scholarships.export');
Route::get('/financial/student-scholarships/search-students', [StudentScholarshipController::class, 'searchStudents'])
    ->middleware('active_permission:student-scholarship.viewAny')
    ->name('financial.student-scholarships.search-students');
Route::post('/financial/student-scholarships/bulk-destroy', [StudentScholarshipController::class, 'bulkDestroy'])
    ->middleware('active_permission:student-scholarship.delete')
    ->name('financial.student-scholarships.bulk-destroy');
Route::get('/financial/student-scholarships/import/template', [StudentScholarshipController::class, 'importTemplate'])
    ->middleware('active_permission:student-scholarship.create')
    ->name('financial.student-scholarships.import-template');
Route::post('/financial/student-scholarships/import', [StudentScholarshipController::class, 'import'])
    ->middleware('active_permission:student-scholarship.create')
    ->name('financial.student-scholarships.import');
Route::get('/financial/student-scholarships', [StudentScholarshipController::class, 'index'])
    ->middleware('active_permission:student-scholarship.viewAny')
    ->name('financial.student-scholarships.index');
Route::get('/financial/student-scholarships/create', [StudentScholarshipController::class, 'create'])
    ->middleware('active_permission:student-scholarship.create')
    ->name('financial.student-scholarships.create');
Route::post('/financial/student-scholarships', [StudentScholarshipController::class, 'store'])
    ->middleware('active_permission:student-scholarship.create')
    ->name('financial.student-scholarships.store');
Route::get('/financial/student-scholarships/{studentScholarship}/edit', [StudentScholarshipController::class, 'edit'])
    ->middleware('active_permission:student-scholarship.update')
    ->name('financial.student-scholarships.edit');
Route::put('/financial/student-scholarships/{studentScholarship}', [StudentScholarshipController::class, 'update'])
    ->middleware('active_permission:student-scholarship.update')
    ->name('financial.student-scholarships.update');
Route::delete('/financial/student-scholarships/{studentScholarship}', [StudentScholarshipController::class, 'destroy'])
    ->middleware('active_permission:student-scholarship.delete')
    ->name('financial.student-scholarships.destroy');

// Saldo Deposit (Inertia React) — paritas Livewire.
Route::get('/financial/student-credits/export', [StudentCreditController::class, 'export'])
    ->middleware('active_permission:student-credit.viewAny')
    ->name('financial.student-credits.export');
Route::get('/financial/student-credits/search-students', [StudentCreditController::class, 'searchStudents'])
    ->middleware('active_permission:student-credit.viewAny')
    ->name('financial.student-credits.search-students');
Route::post('/financial/student-credits/record', [StudentCreditController::class, 'record'])
    ->middleware('active_permission:student-credit.create')
    ->name('financial.student-credits.record');
Route::get('/financial/student-credits', [StudentCreditController::class, 'index'])
    ->middleware('active_permission:student-credit.viewAny')
    ->name('financial.student-credits.index');

// Blokir Akademik (Inertia React, tinjau + penanganan) — paritas Livewire.
Route::get('/financial/financial-holds/export', [FinancialHoldController::class, 'export'])
    ->middleware('active_permission:financial-hold.viewAny')
    ->name('financial.financial-holds.export');
Route::get('/financial/financial-holds', [FinancialHoldController::class, 'index'])
    ->middleware('active_permission:financial-hold.viewAny')
    ->name('financial.financial-holds.index');
Route::get('/financial/financial-holds/{financialHold}', [FinancialHoldController::class, 'show'])
    ->middleware('active_permission:financial-hold.view')
    ->name('financial.financial-holds.show');
Route::post('/financial/financial-holds/{financialHold}/release', [FinancialHoldController::class, 'release'])
    ->middleware('active_permission:financial-hold.update')
    ->name('financial.financial-holds.release');
Route::post('/financial/financial-holds/{financialHold}/waive', [FinancialHoldController::class, 'waive'])
    ->middleware('active_permission:financial-hold.update')
    ->name('financial.financial-holds.waive');

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
