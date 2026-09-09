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
    // users, permissions, roles, menus, settings, notification-logs & activity-logs → Inertia React.
    if (in_array($resource['plural'], ['permissions', 'roles', 'users', 'menus', 'settings', 'notification-logs', 'activity-logs']) && in_array($resource['area'], ['access', 'system'])) {
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
Route::livewire('/academic/course-offerings/{offeringId}/attendance-sessions/{id}', 'admin.academic.attendance-sessions.show')
    ->middleware('active_permission:course-offering.view')
    ->name('academic.attendance-sessions.show');

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
