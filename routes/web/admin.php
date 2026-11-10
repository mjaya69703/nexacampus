<?php

use App\Http\Controllers\Admin\Access\PermissionController;
use App\Http\Controllers\Admin\Access\RoleController;
use App\Http\Controllers\Admin\Access\UserController;
use App\Http\Controllers\Admin\System\ActivityLogController;
use App\Http\Controllers\Admin\System\MenuController;
use App\Http\Controllers\Admin\System\NotificationLogController;
use App\Http\Controllers\Admin\System\SettingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AdminDashboardController;
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
use App\Http\Controllers\Admin\Academic\StudentRegistrationController;
use App\Http\Controllers\Admin\Academic\StudyPlanController;
use App\Http\Controllers\Admin\Academic\FacultyController;
use App\Http\Controllers\Admin\Academic\StudyProgramController;
use App\Http\Controllers\Admin\Campus\AdminCampusExportController;
use App\Http\Controllers\Admin\Campus\AssetCategoryController;
use App\Http\Controllers\Admin\Campus\BuildingController;
use App\Http\Controllers\Admin\Campus\CampusAssetController;
use App\Http\Controllers\Admin\Campus\CampusLocationController;
use App\Http\Controllers\Admin\Campus\MaintenanceController;
use App\Http\Controllers\Admin\Campus\RoomController;
use App\Http\Controllers\Admin\Campus\RoomReservationController;
use App\Http\Controllers\Admin\Publication\AdminPublicationExportController;
use App\Http\Controllers\Admin\Publication\AgendaController;
use App\Http\Controllers\Admin\Publication\AnnouncementController;
use App\Http\Controllers\Admin\Publication\FaqController;
use App\Http\Controllers\Admin\Publication\GalleryController;
use App\Http\Controllers\Admin\Publication\NewsController;
use App\Http\Controllers\Admin\Publication\PublicationCategoryController;
use App\Http\Controllers\Admin\Admission\AcceptanceLetterController;
use App\Http\Controllers\Admin\Admission\AdmissionDocumentController;
use App\Http\Controllers\Admin\Admission\AdmissionApplicationController;
use App\Http\Controllers\Admin\Admission\AdmissionExamScheduleController;
use App\Http\Controllers\Admin\Admission\AdmissionPeriodController;
use App\Http\Controllers\Admin\Admission\AdmissionQuotaController;
use App\Http\Controllers\Admin\Admission\AdmissionSelectionController;
use App\Http\Controllers\Admin\Admission\NimGenerationRuleController;
use App\Http\Controllers\Admin\Alumni\AlumniEventController;
use App\Http\Controllers\Admin\Alumni\AlumniProfileController;
use App\Http\Controllers\Admin\Alumni\EmployerPartnerController;
use App\Http\Controllers\Admin\Alumni\JobPostingController;
use App\Http\Controllers\Admin\Alumni\TracerStudyCampaignController;
use App\Http\Controllers\Admin\Alumni\TracerStudyResponseController;
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
use App\Http\Controllers\Admin\StudentService\GraduationApplicationController;
use App\Http\Controllers\Admin\StudentService\GraduationBatchController;
use App\Http\Controllers\Admin\StudentService\GraduationDocumentRequirementController;
use App\Http\Controllers\Admin\StudentService\GraduationPolicyController;
use App\Http\Controllers\Admin\StudentService\ServiceLetterRequestController;
use App\Http\Controllers\Admin\StudentService\ServiceLetterTypeController;
use App\Http\Controllers\Admin\StudentService\StudentComplaintCategoryController;
use App\Http\Controllers\Admin\StudentService\StudentComplaintController;
use App\Http\Controllers\Admin\StudentService\StudentLeaveApplicationController;
use App\Http\Controllers\Admin\StudentService\StudentTransferRequestController;
use App\Http\Controllers\Admin\Organization\EmployeeLeaveBalanceController;
use App\Http\Controllers\Admin\Organization\EmployeeLeaveTypeController;
use App\Http\Controllers\Admin\Organization\EdomPeriodController;
use App\Http\Controllers\Admin\Organization\EdomQuestionController;
use App\Http\Controllers\Admin\Organization\LecturerPerformanceRubricController;
use App\Http\Controllers\Admin\Organization\LecturerWorkloadPeriodController;
use App\Http\Controllers\Admin\Organization\LecturerWorkloadRuleController;
use App\Http\Controllers\Admin\Organization\OrganizationalPositionController;
use App\Http\Controllers\Admin\Organization\UserDevelopmentRecordController;
use App\Http\Controllers\Admin\Organization\WorkUnitController;
use App\Support\ResourceRegistry;
use Illuminate\Support\Facades\Route;

foreach (ResourceRegistry::all() as $resource) {
    // users, permissions, roles, menus, settings, faculties, notification-logs & activity-logs → Inertia React.
    // Batch-1 publikasi: categories & faqs → Inertia React.
    if (in_array($resource['plural'], ['permissions', 'roles', 'users', 'menus', 'settings', 'faculties', 'study-programs', 'courses', 'academic-years', 'academic-periods', 'academic-advisor-assignments', 'curriculums', 'course-offerings', 'course-schedules', 'student-registrations', 'study-plans', 'student-grades', 'transcripts', 'notification-logs', 'activity-logs']) && in_array($resource['area'], ['access', 'system', 'academic'])) {
        continue;
    }

    if (in_array($resource['plural'], ['categories', 'faqs', 'announcements', 'news', 'agendas', 'galleries']) && ($resource['area'] ?? null) === 'publication') {
        continue;
    }

    if (in_array($resource['plural'], ['campus-locations', 'buildings', 'rooms', 'campus-assets', 'room-reservations', 'facility-maintenances', 'asset-categories']) && ($resource['area'] ?? null) === 'campus') {
        continue;
    }

    // Batch AD-1: periode, kuota & aturan NIM → Inertia React.
    if (in_array($resource['plural'], ['admission-periods', 'admission-quotas', 'nim-generation-rules']) && ($resource['area'] ?? null) === 'admission') {
        continue;
    }

    // Batch AD-2: jadwal ujian, pendaftar & seleksi → Inertia React.
    if (in_array($resource['plural'], ['admission-exam-schedules', 'admission-applications', 'admission-selection']) && ($resource['area'] ?? null) === 'admission') {
        continue;
    }

    // Batch AL-1: profil alumni, mitra perusahaan & lowongan → Inertia React.
    if (in_array($resource['plural'], ['alumni-profiles', 'employer-partners', 'job-postings']) && ($resource['area'] ?? null) === 'alumni') {
        continue;
    }

    // Batch AL-2: event, kampanye & respon tracer study → Inertia React.
    if (in_array($resource['plural'], ['alumni-events', 'tracer-study-campaigns', 'tracer-study-responses']) && ($resource['area'] ?? null) === 'alumni') {
        continue;
    }

    // Batch SS-1: seluruh CRUD layanan mahasiswa → Inertia React.
    if (in_array($resource['plural'], ['service-letter-types', 'service-letter-requests', 'leave-applications', 'transfer-requests', 'graduation-batches', 'graduation-applications', 'graduation-policies', 'graduation-document-requirements', 'student-complaint-categories', 'student-complaints']) && ($resource['area'] ?? null) === 'student-services') {
        continue;
    }

    // Batch OR-1: master jabatan & jenis cuti pegawai → Inertia React.
    if (in_array($resource['plural'], ['organizational-positions', 'employee-leave-types']) && ($resource['area'] ?? null) === 'organization') {
        continue;
    }

    // Batch OR-2: periode & bank pertanyaan EDOM → Inertia React.
    if (in_array($resource['plural'], ['edom-periods', 'edom-questions']) && ($resource['area'] ?? null) === 'organization') {
        continue;
    }

    // Batch OR-3: aturan SKS BKD & rubrik performa dosen → Inertia React.
    if (in_array($resource['plural'], ['lecturer-workload-rules', 'lecturer-performance-rubrics']) && ($resource['area'] ?? null) === 'organization') {
        continue;
    }

    // Batch OR-4: unit kerja & sertifikasi/pelatihan pegawai → Inertia React.
    if (in_array($resource['plural'], ['work-units', 'user-development-records']) && ($resource['area'] ?? null) === 'organization') {
        continue;
    }

    // Batch OR-5: periode BKD & saldo cuti pegawai → Inertia React.
    if (in_array($resource['plural'], ['lecturer-workload-periods', 'employee-leave-balances']) && ($resource['area'] ?? null) === 'organization') {
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

// CRUD Layanan Mahasiswa (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/student-services/service-letter-types', [ServiceLetterTypeController::class, 'index'])
    ->middleware('active_permission:service-letter-type.viewAny')
    ->name('student-services.letter-types.index');
Route::get('/student-services/service-letter-types/create', [ServiceLetterTypeController::class, 'create'])
    ->middleware('active_permission:service-letter-type.create')
    ->name('student-services.letter-types.create');
Route::post('/student-services/service-letter-types', [ServiceLetterTypeController::class, 'store'])
    ->middleware('active_permission:service-letter-type.create')
    ->name('student-services.letter-types.store');
Route::post('/student-services/service-letter-types/bulk-destroy', [ServiceLetterTypeController::class, 'bulkDestroy'])
    ->middleware('active_permission:service-letter-type.delete')
    ->name('student-services.letter-types.bulk-destroy');
Route::get('/student-services/service-letter-types/{id}/edit', [ServiceLetterTypeController::class, 'edit'])
    ->middleware('active_permission:service-letter-type.update')
    ->name('student-services.letter-types.edit');
Route::put('/student-services/service-letter-types/{id}', [ServiceLetterTypeController::class, 'update'])
    ->middleware('active_permission:service-letter-type.update')
    ->name('student-services.letter-types.update');
Route::post('/student-services/service-letter-types/{id}/toggle', [ServiceLetterTypeController::class, 'toggle'])
    ->middleware('active_permission:service-letter-type.update')
    ->name('student-services.letter-types.toggle');
Route::delete('/student-services/service-letter-types/{id}', [ServiceLetterTypeController::class, 'destroy'])
    ->middleware('active_permission:service-letter-type.delete')
    ->name('student-services.letter-types.destroy');

Route::get('/student-services/service-letter-requests', [ServiceLetterRequestController::class, 'index'])
    ->middleware('active_permission:service-letter-request.viewAny')
    ->name('student-services.letter-requests.index');
Route::get('/student-services/service-letter-requests/{id}', [ServiceLetterRequestController::class, 'show'])
    ->middleware('active_permission:service-letter-request.view')
    ->name('student-services.letter-requests.show');
Route::post('/student-services/service-letter-requests/{id}/mark-under-review', [ServiceLetterRequestController::class, 'markUnderReview'])
    ->middleware('active_permission:service-letter-request.update')
    ->name('student-services.letter-requests.mark-under-review');
Route::post('/student-services/service-letter-requests/{id}/approve', [ServiceLetterRequestController::class, 'approve'])
    ->middleware('active_permission:service-letter-request.update')
    ->name('student-services.letter-requests.approve');
Route::post('/student-services/service-letter-requests/{id}/request-revision', [ServiceLetterRequestController::class, 'requestRevision'])
    ->middleware('active_permission:service-letter-request.update')
    ->name('student-services.letter-requests.request-revision');
Route::post('/student-services/service-letter-requests/{id}/reject', [ServiceLetterRequestController::class, 'reject'])
    ->middleware('active_permission:service-letter-request.update')
    ->name('student-services.letter-requests.reject');
Route::post('/student-services/service-letter-requests/{id}/issue', [ServiceLetterRequestController::class, 'issue'])
    ->middleware('active_permission:service-letter-request.update')
    ->name('student-services.letter-requests.issue');

Route::get('/student-services/leave-applications', [StudentLeaveApplicationController::class, 'index'])
    ->middleware('active_permission:leave-application.viewAny')
    ->name('student-services.leave-applications.index');
Route::get('/student-services/leave-applications/{id}', [StudentLeaveApplicationController::class, 'show'])
    ->middleware('active_permission:leave-application.view')
    ->name('student-services.leave-applications.show');
Route::post('/student-services/leave-applications/{id}/mark-under-review', [StudentLeaveApplicationController::class, 'markUnderReview'])
    ->middleware('active_permission:leave-application.update')
    ->name('student-services.leave-applications.mark-under-review');
Route::post('/student-services/leave-applications/{id}/approve', [StudentLeaveApplicationController::class, 'approve'])
    ->middleware('active_permission:leave-application.update')
    ->name('student-services.leave-applications.approve');
Route::post('/student-services/leave-applications/{id}/request-revision', [StudentLeaveApplicationController::class, 'requestRevision'])
    ->middleware('active_permission:leave-application.update')
    ->name('student-services.leave-applications.request-revision');
Route::post('/student-services/leave-applications/{id}/reject', [StudentLeaveApplicationController::class, 'reject'])
    ->middleware('active_permission:leave-application.update')
    ->name('student-services.leave-applications.reject');
Route::post('/student-services/leave-applications/{id}/activate', [StudentLeaveApplicationController::class, 'activate'])
    ->middleware('active_permission:leave-application.update')
    ->name('student-services.leave-applications.activate');
Route::post('/student-services/leave-applications/{id}/return-to-active', [StudentLeaveApplicationController::class, 'returnToActive'])
    ->middleware('active_permission:leave-application.update')
    ->name('student-services.leave-applications.return-to-active');

Route::get('/student-services/transfer-requests', [StudentTransferRequestController::class, 'index'])
    ->middleware('active_permission:transfer-request.viewAny')
    ->name('student-services.transfer-requests.index');
Route::get('/student-services/transfer-requests/{id}', [StudentTransferRequestController::class, 'show'])
    ->middleware('active_permission:transfer-request.view')
    ->name('student-services.transfer-requests.show');
Route::post('/student-services/transfer-requests/{id}/mark-under-review', [StudentTransferRequestController::class, 'markUnderReview'])
    ->middleware('active_permission:transfer-request.update')
    ->name('student-services.transfer-requests.mark-under-review');
Route::post('/student-services/transfer-requests/{id}/approve', [StudentTransferRequestController::class, 'approve'])
    ->middleware('active_permission:transfer-request.update')
    ->name('student-services.transfer-requests.approve');
Route::post('/student-services/transfer-requests/{id}/request-revision', [StudentTransferRequestController::class, 'requestRevision'])
    ->middleware('active_permission:transfer-request.update')
    ->name('student-services.transfer-requests.request-revision');
Route::post('/student-services/transfer-requests/{id}/reject', [StudentTransferRequestController::class, 'reject'])
    ->middleware('active_permission:transfer-request.update')
    ->name('student-services.transfer-requests.reject');
Route::post('/student-services/transfer-requests/{id}/apply-transfer', [StudentTransferRequestController::class, 'applyTransfer'])
    ->middleware('active_permission:transfer-request.update')
    ->name('student-services.transfer-requests.apply-transfer');

Route::get('/student-services/graduation-batches', [GraduationBatchController::class, 'index'])
    ->middleware('active_permission:graduation-batch.viewAny')
    ->name('student-services.graduation-batches.index');
Route::get('/student-services/graduation-batches/create', [GraduationBatchController::class, 'create'])
    ->middleware('active_permission:graduation-batch.create')
    ->name('student-services.graduation-batches.create');
Route::post('/student-services/graduation-batches', [GraduationBatchController::class, 'store'])
    ->middleware('active_permission:graduation-batch.create')
    ->name('student-services.graduation-batches.store');
Route::get('/student-services/graduation-batches/{id}', [GraduationBatchController::class, 'show'])
    ->middleware('active_permission:graduation-batch.view')
    ->name('student-services.graduation-batches.show');
Route::get('/student-services/graduation-batches/{id}/edit', [GraduationBatchController::class, 'edit'])
    ->middleware('active_permission:graduation-batch.update')
    ->name('student-services.graduation-batches.edit');
Route::put('/student-services/graduation-batches/{id}', [GraduationBatchController::class, 'update'])
    ->middleware('active_permission:graduation-batch.update')
    ->name('student-services.graduation-batches.update');
Route::post('/student-services/graduation-batches/{id}/bulk-finalize', [GraduationBatchController::class, 'bulkFinalize'])
    ->middleware('active_permission:graduation-batch.update')
    ->name('student-services.graduation-batches.bulk-finalize');

Route::get('/student-services/graduation-applications', [GraduationApplicationController::class, 'index'])
    ->middleware('active_permission:graduation-application.viewAny')
    ->name('student-services.graduation-applications.index');
Route::get('/student-services/graduation-applications/{id}', [GraduationApplicationController::class, 'show'])
    ->middleware('active_permission:graduation-application.view')
    ->name('student-services.graduation-applications.show');
Route::post('/student-services/graduation-applications/{id}/mark-under-review', [GraduationApplicationController::class, 'markUnderReview'])
    ->middleware('active_permission:graduation-application.update')
    ->name('student-services.graduation-applications.mark-under-review');
Route::post('/student-services/graduation-applications/{id}/save-checklist', [GraduationApplicationController::class, 'saveChecklist'])
    ->middleware('active_permission:graduation-application.update')
    ->name('student-services.graduation-applications.save-checklist');
Route::post('/student-services/graduation-applications/{id}/verify-document', [GraduationApplicationController::class, 'verifyDocument'])
    ->middleware('active_permission:graduation-application.update')
    ->name('student-services.graduation-applications.verify-document');
Route::post('/student-services/graduation-applications/{id}/approve', [GraduationApplicationController::class, 'approve'])
    ->middleware('active_permission:graduation-application.update')
    ->name('student-services.graduation-applications.approve');
Route::post('/student-services/graduation-applications/{id}/request-revision', [GraduationApplicationController::class, 'requestRevision'])
    ->middleware('active_permission:graduation-application.update')
    ->name('student-services.graduation-applications.request-revision');
Route::post('/student-services/graduation-applications/{id}/reject', [GraduationApplicationController::class, 'reject'])
    ->middleware('active_permission:graduation-application.update')
    ->name('student-services.graduation-applications.reject');
Route::post('/student-services/graduation-applications/{id}/finalize', [GraduationApplicationController::class, 'finalize'])
    ->middleware('active_permission:graduation-application.update')
    ->name('student-services.graduation-applications.finalize');

Route::get('/student-services/graduation-policies', [GraduationPolicyController::class, 'index'])
    ->middleware('active_permission:graduation-policy.viewAny')
    ->name('student-services.graduation-policies.index');
Route::get('/student-services/graduation-policies/create', [GraduationPolicyController::class, 'create'])
    ->middleware('active_permission:graduation-policy.create')
    ->name('student-services.graduation-policies.create');
Route::post('/student-services/graduation-policies', [GraduationPolicyController::class, 'store'])
    ->middleware('active_permission:graduation-policy.create')
    ->name('student-services.graduation-policies.store');
Route::get('/student-services/graduation-policies/{id}/edit', [GraduationPolicyController::class, 'edit'])
    ->middleware('active_permission:graduation-policy.update')
    ->name('student-services.graduation-policies.edit');
Route::put('/student-services/graduation-policies/{id}', [GraduationPolicyController::class, 'update'])
    ->middleware('active_permission:graduation-policy.update')
    ->name('student-services.graduation-policies.update');
Route::post('/student-services/graduation-policies/{id}/toggle', [GraduationPolicyController::class, 'toggle'])
    ->middleware('active_permission:graduation-policy.update')
    ->name('student-services.graduation-policies.toggle');

Route::get('/student-services/graduation-document-requirements', [GraduationDocumentRequirementController::class, 'index'])
    ->middleware('active_permission:graduation-document-requirement.viewAny')
    ->name('student-services.graduation-document-requirements.index');
Route::get('/student-services/graduation-document-requirements/create', [GraduationDocumentRequirementController::class, 'create'])
    ->middleware('active_permission:graduation-document-requirement.create')
    ->name('student-services.graduation-document-requirements.create');
Route::post('/student-services/graduation-document-requirements', [GraduationDocumentRequirementController::class, 'store'])
    ->middleware('active_permission:graduation-document-requirement.create')
    ->name('student-services.graduation-document-requirements.store');
Route::get('/student-services/graduation-document-requirements/{id}/edit', [GraduationDocumentRequirementController::class, 'edit'])
    ->middleware('active_permission:graduation-document-requirement.update')
    ->name('student-services.graduation-document-requirements.edit');
Route::put('/student-services/graduation-document-requirements/{id}', [GraduationDocumentRequirementController::class, 'update'])
    ->middleware('active_permission:graduation-document-requirement.update')
    ->name('student-services.graduation-document-requirements.update');
Route::post('/student-services/graduation-document-requirements/{id}/toggle', [GraduationDocumentRequirementController::class, 'toggle'])
    ->middleware('active_permission:graduation-document-requirement.update')
    ->name('student-services.graduation-document-requirements.toggle');

Route::get('/student-services/student-complaint-categories', [StudentComplaintCategoryController::class, 'index'])
    ->middleware('active_permission:student-complaint-category.viewAny')
    ->name('student-services.complaint-categories.index');
Route::get('/student-services/student-complaint-categories/create', [StudentComplaintCategoryController::class, 'create'])
    ->middleware('active_permission:student-complaint-category.create')
    ->name('student-services.complaint-categories.create');
Route::post('/student-services/student-complaint-categories', [StudentComplaintCategoryController::class, 'store'])
    ->middleware('active_permission:student-complaint-category.create')
    ->name('student-services.complaint-categories.store');
Route::post('/student-services/student-complaint-categories/bulk-destroy', [StudentComplaintCategoryController::class, 'bulkDestroy'])
    ->middleware('active_permission:student-complaint-category.delete')
    ->name('student-services.complaint-categories.bulk-destroy');
Route::get('/student-services/student-complaint-categories/{id}/edit', [StudentComplaintCategoryController::class, 'edit'])
    ->middleware('active_permission:student-complaint-category.update')
    ->name('student-services.complaint-categories.edit');
Route::put('/student-services/student-complaint-categories/{id}', [StudentComplaintCategoryController::class, 'update'])
    ->middleware('active_permission:student-complaint-category.update')
    ->name('student-services.complaint-categories.update');
Route::post('/student-services/student-complaint-categories/{id}/toggle', [StudentComplaintCategoryController::class, 'toggle'])
    ->middleware('active_permission:student-complaint-category.update')
    ->name('student-services.complaint-categories.toggle');
Route::delete('/student-services/student-complaint-categories/{id}', [StudentComplaintCategoryController::class, 'destroy'])
    ->middleware('active_permission:student-complaint-category.delete')
    ->name('student-services.complaint-categories.destroy');

Route::get('/student-services/student-complaints', [StudentComplaintController::class, 'index'])
    ->middleware('active_permission:student-complaint.viewAny')
    ->name('student-services.complaints.index');
Route::get('/student-services/student-complaints/{id}', [StudentComplaintController::class, 'show'])
    ->middleware('active_permission:student-complaint.view')
    ->name('student-services.complaints.show');
Route::post('/student-services/student-complaints/{id}/assign', [StudentComplaintController::class, 'assign'])
    ->middleware('active_permission:student-complaint.assign|student-complaint.update')
    ->name('student-services.complaints.assign');
Route::post('/student-services/student-complaints/{id}/claim', [StudentComplaintController::class, 'claim'])
    ->middleware('active_permission:student-complaint.assign|student-complaint.update')
    ->name('student-services.complaints.claim');
Route::post('/student-services/student-complaints/{id}/reply', [StudentComplaintController::class, 'reply'])
    ->middleware('active_permission:student-complaint.respond|student-complaint.update')
    ->name('student-services.complaints.reply');
Route::post('/student-services/student-complaints/{id}/set-status', [StudentComplaintController::class, 'setStatus'])
    ->middleware('active_permission:student-complaint.resolve|student-complaint.update')
    ->name('student-services.complaints.set-status');

// CRUD Kepegawaian (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/organization/organizational-positions', [OrganizationalPositionController::class, 'index'])
    ->middleware('active_permission:organizational-position.viewAny')
    ->name('organization.organizational-positions.index');
Route::get('/organization/organizational-positions/create', [OrganizationalPositionController::class, 'create'])
    ->middleware('active_permission:organizational-position.create')
    ->name('organization.organizational-positions.create');
Route::post('/organization/organizational-positions', [OrganizationalPositionController::class, 'store'])
    ->middleware('active_permission:organizational-position.create')
    ->name('organization.organizational-positions.store');
Route::post('/organization/organizational-positions/bulk-destroy', [OrganizationalPositionController::class, 'bulkDestroy'])
    ->middleware('active_permission:organizational-position.delete')
    ->name('organization.organizational-positions.bulk-destroy');
Route::get('/organization/organizational-positions/{id}/edit', [OrganizationalPositionController::class, 'edit'])
    ->middleware('active_permission:organizational-position.update')
    ->name('organization.organizational-positions.edit');
Route::put('/organization/organizational-positions/{id}', [OrganizationalPositionController::class, 'update'])
    ->middleware('active_permission:organizational-position.update')
    ->name('organization.organizational-positions.update');
Route::post('/organization/organizational-positions/{id}/toggle', [OrganizationalPositionController::class, 'toggle'])
    ->middleware('active_permission:organizational-position.update')
    ->name('organization.organizational-positions.toggle');
Route::delete('/organization/organizational-positions/{id}', [OrganizationalPositionController::class, 'destroy'])
    ->middleware('active_permission:organizational-position.delete')
    ->name('organization.organizational-positions.destroy');

Route::get('/organization/employee-leave-types', [EmployeeLeaveTypeController::class, 'index'])
    ->middleware('active_permission:employee-leave-type.viewAny')
    ->name('organization.employee-leave-types.index');
Route::get('/organization/employee-leave-types/create', [EmployeeLeaveTypeController::class, 'create'])
    ->middleware('active_permission:employee-leave-type.create')
    ->name('organization.employee-leave-types.create');
Route::post('/organization/employee-leave-types', [EmployeeLeaveTypeController::class, 'store'])
    ->middleware('active_permission:employee-leave-type.create')
    ->name('organization.employee-leave-types.store');
Route::post('/organization/employee-leave-types/bulk-destroy', [EmployeeLeaveTypeController::class, 'bulkDestroy'])
    ->middleware('active_permission:employee-leave-type.delete')
    ->name('organization.employee-leave-types.bulk-destroy');
Route::get('/organization/employee-leave-types/{id}/edit', [EmployeeLeaveTypeController::class, 'edit'])
    ->middleware('active_permission:employee-leave-type.update')
    ->name('organization.employee-leave-types.edit');
Route::put('/organization/employee-leave-types/{id}', [EmployeeLeaveTypeController::class, 'update'])
    ->middleware('active_permission:employee-leave-type.update')
    ->name('organization.employee-leave-types.update');
Route::post('/organization/employee-leave-types/{id}/toggle', [EmployeeLeaveTypeController::class, 'toggle'])
    ->middleware('active_permission:employee-leave-type.update')
    ->name('organization.employee-leave-types.toggle');
Route::delete('/organization/employee-leave-types/{id}', [EmployeeLeaveTypeController::class, 'destroy'])
    ->middleware('active_permission:employee-leave-type.delete')
    ->name('organization.employee-leave-types.destroy');

Route::get('/organization/edom-periods', [EdomPeriodController::class, 'index'])
    ->middleware('active_permission:edom-period.viewAny')
    ->name('organization.edom-periods.index');
Route::get('/organization/edom-periods/create', [EdomPeriodController::class, 'create'])
    ->middleware('active_permission:edom-period.create')
    ->name('organization.edom-periods.create');
Route::post('/organization/edom-periods', [EdomPeriodController::class, 'store'])
    ->middleware('active_permission:edom-period.create')
    ->name('organization.edom-periods.store');
Route::post('/organization/edom-periods/bulk-destroy', [EdomPeriodController::class, 'bulkDestroy'])
    ->middleware('active_permission:edom-period.delete')
    ->name('organization.edom-periods.bulk-destroy');
Route::get('/organization/edom-periods/{id}/edit', [EdomPeriodController::class, 'edit'])
    ->middleware('active_permission:edom-period.update')
    ->name('organization.edom-periods.edit');
Route::put('/organization/edom-periods/{id}', [EdomPeriodController::class, 'update'])
    ->middleware('active_permission:edom-period.update')
    ->name('organization.edom-periods.update');
Route::delete('/organization/edom-periods/{id}', [EdomPeriodController::class, 'destroy'])
    ->middleware('active_permission:edom-period.delete')
    ->name('organization.edom-periods.destroy');

Route::get('/organization/edom-questions', [EdomQuestionController::class, 'index'])
    ->middleware('active_permission:edom-question.viewAny')
    ->name('organization.edom-questions.index');
Route::get('/organization/edom-questions/create', [EdomQuestionController::class, 'create'])
    ->middleware('active_permission:edom-question.create')
    ->name('organization.edom-questions.create');
Route::post('/organization/edom-questions', [EdomQuestionController::class, 'store'])
    ->middleware('active_permission:edom-question.create')
    ->name('organization.edom-questions.store');
Route::post('/organization/edom-questions/bulk-destroy', [EdomQuestionController::class, 'bulkDestroy'])
    ->middleware('active_permission:edom-question.delete')
    ->name('organization.edom-questions.bulk-destroy');
Route::get('/organization/edom-questions/{id}/edit', [EdomQuestionController::class, 'edit'])
    ->middleware('active_permission:edom-question.update')
    ->name('organization.edom-questions.edit');
Route::put('/organization/edom-questions/{id}', [EdomQuestionController::class, 'update'])
    ->middleware('active_permission:edom-question.update')
    ->name('organization.edom-questions.update');
Route::post('/organization/edom-questions/{id}/toggle', [EdomQuestionController::class, 'toggle'])
    ->middleware('active_permission:edom-question.update')
    ->name('organization.edom-questions.toggle');
Route::delete('/organization/edom-questions/{id}', [EdomQuestionController::class, 'destroy'])
    ->middleware('active_permission:edom-question.delete')
    ->name('organization.edom-questions.destroy');

Route::get('/organization/lecturer-workload-rules', [LecturerWorkloadRuleController::class, 'index'])
    ->middleware('active_permission:lecturer-workload-rule.viewAny')
    ->name('organization.lecturer-workload-rules.index');
Route::get('/organization/lecturer-workload-rules/create', [LecturerWorkloadRuleController::class, 'create'])
    ->middleware('active_permission:lecturer-workload-rule.create')
    ->name('organization.lecturer-workload-rules.create');
Route::post('/organization/lecturer-workload-rules', [LecturerWorkloadRuleController::class, 'store'])
    ->middleware('active_permission:lecturer-workload-rule.create')
    ->name('organization.lecturer-workload-rules.store');
Route::post('/organization/lecturer-workload-rules/bulk-destroy', [LecturerWorkloadRuleController::class, 'bulkDestroy'])
    ->middleware('active_permission:lecturer-workload-rule.delete')
    ->name('organization.lecturer-workload-rules.bulk-destroy');
Route::get('/organization/lecturer-workload-rules/{id}/edit', [LecturerWorkloadRuleController::class, 'edit'])
    ->middleware('active_permission:lecturer-workload-rule.update')
    ->name('organization.lecturer-workload-rules.edit');
Route::put('/organization/lecturer-workload-rules/{id}', [LecturerWorkloadRuleController::class, 'update'])
    ->middleware('active_permission:lecturer-workload-rule.update')
    ->name('organization.lecturer-workload-rules.update');
Route::delete('/organization/lecturer-workload-rules/{id}', [LecturerWorkloadRuleController::class, 'destroy'])
    ->middleware('active_permission:lecturer-workload-rule.delete')
    ->name('organization.lecturer-workload-rules.destroy');

Route::get('/organization/lecturer-performance-rubrics', [LecturerPerformanceRubricController::class, 'index'])
    ->middleware('active_permission:lecturer-performance-rubric.viewAny')
    ->name('organization.lecturer-performance-rubrics.index');
Route::get('/organization/lecturer-performance-rubrics/create', [LecturerPerformanceRubricController::class, 'create'])
    ->middleware('active_permission:lecturer-performance-rubric.create')
    ->name('organization.lecturer-performance-rubrics.create');
Route::post('/organization/lecturer-performance-rubrics', [LecturerPerformanceRubricController::class, 'store'])
    ->middleware('active_permission:lecturer-performance-rubric.create')
    ->name('organization.lecturer-performance-rubrics.store');
Route::get('/organization/lecturer-performance-rubrics/{id}/edit', [LecturerPerformanceRubricController::class, 'edit'])
    ->middleware('active_permission:lecturer-performance-rubric.update')
    ->name('organization.lecturer-performance-rubrics.edit');
Route::put('/organization/lecturer-performance-rubrics/{id}', [LecturerPerformanceRubricController::class, 'update'])
    ->middleware('active_permission:lecturer-performance-rubric.update')
    ->name('organization.lecturer-performance-rubrics.update');
Route::post('/organization/lecturer-performance-rubrics/{id}/toggle', [LecturerPerformanceRubricController::class, 'toggle'])
    ->middleware('active_permission:lecturer-performance-rubric.update')
    ->name('organization.lecturer-performance-rubrics.toggle');
Route::delete('/organization/lecturer-performance-rubrics/{id}', [LecturerPerformanceRubricController::class, 'destroy'])
    ->middleware('active_permission:lecturer-performance-rubric.delete')
    ->name('organization.lecturer-performance-rubrics.destroy');

Route::get('/organization/work-units', [WorkUnitController::class, 'index'])
    ->middleware('active_permission:work-unit.viewAny')
    ->name('organization.work-units.index');
Route::get('/organization/work-units/create', [WorkUnitController::class, 'create'])
    ->middleware('active_permission:work-unit.create')
    ->name('organization.work-units.create');
Route::get('/organization/work-units/search-users', [WorkUnitController::class, 'searchUsers'])
    ->middleware('active_permission:work-unit.create|work-unit.update')
    ->name('organization.work-units.search-users');
Route::post('/organization/work-units', [WorkUnitController::class, 'store'])
    ->middleware('active_permission:work-unit.create')
    ->name('organization.work-units.store');
Route::post('/organization/work-units/bulk-destroy', [WorkUnitController::class, 'bulkDestroy'])
    ->middleware('active_permission:work-unit.delete')
    ->name('organization.work-units.bulk-destroy');
Route::get('/organization/work-units/{id}/edit', [WorkUnitController::class, 'edit'])
    ->middleware('active_permission:work-unit.update')
    ->name('organization.work-units.edit');
Route::put('/organization/work-units/{id}', [WorkUnitController::class, 'update'])
    ->middleware('active_permission:work-unit.update')
    ->name('organization.work-units.update');
Route::post('/organization/work-units/{id}/toggle', [WorkUnitController::class, 'toggle'])
    ->middleware('active_permission:work-unit.update')
    ->name('organization.work-units.toggle');
Route::delete('/organization/work-units/{id}', [WorkUnitController::class, 'destroy'])
    ->middleware('active_permission:work-unit.delete')
    ->name('organization.work-units.destroy');

Route::get('/organization/user-development-records', [UserDevelopmentRecordController::class, 'index'])
    ->middleware('active_permission:user-development-record.viewAny')
    ->name('organization.user-development-records.index');
Route::get('/organization/user-development-records/create', [UserDevelopmentRecordController::class, 'create'])
    ->middleware('active_permission:user-development-record.create')
    ->name('organization.user-development-records.create');
Route::get('/organization/user-development-records/search-users', [UserDevelopmentRecordController::class, 'searchUsers'])
    ->middleware('active_permission:user-development-record.create')
    ->name('organization.user-development-records.search-users');
Route::post('/organization/user-development-records', [UserDevelopmentRecordController::class, 'store'])
    ->middleware('active_permission:user-development-record.create')
    ->name('organization.user-development-records.store');
Route::post('/organization/user-development-records/bulk-destroy', [UserDevelopmentRecordController::class, 'bulkDestroy'])
    ->middleware('active_permission:user-development-record.delete')
    ->name('organization.user-development-records.bulk-destroy');
Route::get('/organization/user-development-records/{id}/edit', [UserDevelopmentRecordController::class, 'edit'])
    ->middleware('active_permission:user-development-record.update')
    ->name('organization.user-development-records.edit');
Route::put('/organization/user-development-records/{id}', [UserDevelopmentRecordController::class, 'update'])
    ->middleware('active_permission:user-development-record.update')
    ->name('organization.user-development-records.update');
Route::post('/organization/user-development-records/{id}/verify', [UserDevelopmentRecordController::class, 'verify'])
    ->middleware('active_permission:user-development-record.update')
    ->name('organization.user-development-records.verify');
Route::post('/organization/user-development-records/{id}/unverify', [UserDevelopmentRecordController::class, 'unverify'])
    ->middleware('active_permission:user-development-record.update')
    ->name('organization.user-development-records.unverify');
Route::delete('/organization/user-development-records/{id}', [UserDevelopmentRecordController::class, 'destroy'])
    ->middleware('active_permission:user-development-record.delete')
    ->name('organization.user-development-records.destroy');

Route::get('/organization/lecturer-workload-periods', [LecturerWorkloadPeriodController::class, 'index'])
    ->middleware('active_permission:lecturer-workload-period.viewAny')
    ->name('organization.lecturer-workload-periods.index');
Route::get('/organization/lecturer-workload-periods/create', [LecturerWorkloadPeriodController::class, 'create'])
    ->middleware('active_permission:lecturer-workload-period.create')
    ->name('organization.lecturer-workload-periods.create');
Route::post('/organization/lecturer-workload-periods', [LecturerWorkloadPeriodController::class, 'store'])
    ->middleware('active_permission:lecturer-workload-period.create')
    ->name('organization.lecturer-workload-periods.store');
Route::post('/organization/lecturer-workload-periods/bulk-destroy', [LecturerWorkloadPeriodController::class, 'bulkDestroy'])
    ->middleware('active_permission:lecturer-workload-period.delete')
    ->name('organization.lecturer-workload-periods.bulk-destroy');
Route::get('/organization/lecturer-workload-periods/{id}', [LecturerWorkloadPeriodController::class, 'show'])
    ->middleware('active_permission:lecturer-workload-period.view')
    ->name('organization.lecturer-workload-periods.show');
Route::get('/organization/lecturer-workload-periods/{id}/edit', [LecturerWorkloadPeriodController::class, 'edit'])
    ->middleware('active_permission:lecturer-workload-period.update')
    ->name('organization.lecturer-workload-periods.edit');
Route::put('/organization/lecturer-workload-periods/{id}', [LecturerWorkloadPeriodController::class, 'update'])
    ->middleware('active_permission:lecturer-workload-period.update')
    ->name('organization.lecturer-workload-periods.update');
Route::delete('/organization/lecturer-workload-periods/{id}', [LecturerWorkloadPeriodController::class, 'destroy'])
    ->middleware('active_permission:lecturer-workload-period.delete')
    ->name('organization.lecturer-workload-periods.destroy');

Route::get('/organization/employee-leave-balances', [EmployeeLeaveBalanceController::class, 'index'])
    ->middleware('active_permission:employee-leave-balance.viewAny')
    ->name('organization.employee-leave-balances.index');
Route::get('/organization/employee-leave-balances/search-employees', [EmployeeLeaveBalanceController::class, 'searchEmployees'])
    ->middleware('active_permission:employee-leave-balance.create|employee-leave-balance.update')
    ->name('organization.employee-leave-balances.search-employees');
Route::post('/organization/employee-leave-balances', [EmployeeLeaveBalanceController::class, 'save'])
    ->middleware('active_permission:employee-leave-balance.create|employee-leave-balance.update')
    ->name('organization.employee-leave-balances.save');

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

// CRUD Berita (Inertia React) — Batch-3, nama route & middleware paritas Livewire.
Route::get('/publication/news/export', [NewsController::class, 'export'])
    ->middleware('active_permission:news.viewAny')
    ->name('publication.news.export');
Route::get('/publication/news/import/template', [NewsController::class, 'importTemplate'])
    ->middleware('active_permission:news.create')
    ->name('publication.news.import-template');
Route::post('/publication/news/import', [NewsController::class, 'import'])
    ->middleware('active_permission:news.create')
    ->name('publication.news.import');
Route::post('/publication/news/bulk-destroy', [NewsController::class, 'bulkDestroy'])
    ->middleware('active_permission:news.delete')
    ->name('publication.news.bulk-destroy');
Route::post('/publication/news/bulk-restore', [NewsController::class, 'bulkRestore'])
    ->middleware('active_permission:news.update|news.delete')
    ->name('publication.news.bulk-restore');
Route::post('/publication/news/bulk-force-destroy', [NewsController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:news.update|news.delete')
    ->name('publication.news.bulk-force-destroy');
Route::post('/publication/news/{id}/restore', [NewsController::class, 'restore'])
    ->middleware('active_permission:news.update|news.delete')
    ->name('publication.news.restore');
Route::delete('/publication/news/{id}/force', [NewsController::class, 'forceDestroy'])
    ->middleware('active_permission:news.update|news.delete')
    ->name('publication.news.force-destroy');
Route::post('/publication/news/{news}/toggle', [NewsController::class, 'toggle'])
    ->middleware('active_permission:news.update')
    ->name('publication.news.toggle');
Route::get('/publication/news', [NewsController::class, 'index'])
    ->middleware('active_permission:news.viewAny')
    ->name('publication.news.index');
Route::get('/publication/news/create', [NewsController::class, 'create'])
    ->middleware('active_permission:news.create')
    ->name('publication.news.create');
Route::post('/publication/news', [NewsController::class, 'store'])
    ->middleware('active_permission:news.create')
    ->name('publication.news.store');
Route::get('/publication/news/{news}/edit', [NewsController::class, 'edit'])
    ->middleware('active_permission:news.update')
    ->name('publication.news.edit');
Route::get('/publication/news/{news}', [NewsController::class, 'show'])
    ->middleware('active_permission:news.view')
    ->name('publication.news.show');
Route::put('/publication/news/{news}', [NewsController::class, 'update'])
    ->middleware('active_permission:news.update')
    ->name('publication.news.update');
Route::delete('/publication/news/{news}', [NewsController::class, 'destroy'])
    ->middleware('active_permission:news.delete')
    ->name('publication.news.destroy');

// CRUD Agenda (Inertia React) — Batch-3, nama route & middleware paritas Livewire.
Route::get('/publication/agendas/export', [AgendaController::class, 'export'])
    ->middleware('active_permission:agenda.viewAny')
    ->name('publication.agendas.export');
Route::get('/publication/agendas/import/template', [AgendaController::class, 'importTemplate'])
    ->middleware('active_permission:agenda.create')
    ->name('publication.agendas.import-template');
Route::post('/publication/agendas/import', [AgendaController::class, 'import'])
    ->middleware('active_permission:agenda.create')
    ->name('publication.agendas.import');
Route::post('/publication/agendas/bulk-destroy', [AgendaController::class, 'bulkDestroy'])
    ->middleware('active_permission:agenda.delete')
    ->name('publication.agendas.bulk-destroy');
Route::post('/publication/agendas/bulk-restore', [AgendaController::class, 'bulkRestore'])
    ->middleware('active_permission:agenda.update|agenda.delete')
    ->name('publication.agendas.bulk-restore');
Route::post('/publication/agendas/bulk-force-destroy', [AgendaController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:agenda.update|agenda.delete')
    ->name('publication.agendas.bulk-force-destroy');
Route::post('/publication/agendas/{id}/restore', [AgendaController::class, 'restore'])
    ->middleware('active_permission:agenda.update|agenda.delete')
    ->name('publication.agendas.restore');
Route::delete('/publication/agendas/{id}/force', [AgendaController::class, 'forceDestroy'])
    ->middleware('active_permission:agenda.update|agenda.delete')
    ->name('publication.agendas.force-destroy');
Route::post('/publication/agendas/{agenda}/toggle', [AgendaController::class, 'toggle'])
    ->middleware('active_permission:agenda.update')
    ->name('publication.agendas.toggle');
Route::get('/publication/agendas', [AgendaController::class, 'index'])
    ->middleware('active_permission:agenda.viewAny')
    ->name('publication.agendas.index');
Route::get('/publication/agendas/create', [AgendaController::class, 'create'])
    ->middleware('active_permission:agenda.create')
    ->name('publication.agendas.create');
Route::post('/publication/agendas', [AgendaController::class, 'store'])
    ->middleware('active_permission:agenda.create')
    ->name('publication.agendas.store');
Route::get('/publication/agendas/{agenda}/edit', [AgendaController::class, 'edit'])
    ->middleware('active_permission:agenda.update')
    ->name('publication.agendas.edit');
Route::get('/publication/agendas/{agenda}', [AgendaController::class, 'show'])
    ->middleware('active_permission:agenda.view')
    ->name('publication.agendas.show');
Route::put('/publication/agendas/{agenda}', [AgendaController::class, 'update'])
    ->middleware('active_permission:agenda.update')
    ->name('publication.agendas.update');
Route::delete('/publication/agendas/{agenda}', [AgendaController::class, 'destroy'])
    ->middleware('active_permission:agenda.delete')
    ->name('publication.agendas.destroy');

// CRUD Album Galeri (Inertia React) — Batch-4, nama route & middleware paritas Livewire.
Route::get('/publication/galleries/export', [GalleryController::class, 'export'])
    ->middleware('active_permission:gallery.viewAny')
    ->name('publication.galleries.export');
Route::get('/publication/galleries/import/template', [GalleryController::class, 'importTemplate'])
    ->middleware('active_permission:gallery.create')
    ->name('publication.galleries.import-template');
Route::post('/publication/galleries/import', [GalleryController::class, 'import'])
    ->middleware('active_permission:gallery.create')
    ->name('publication.galleries.import');
Route::post('/publication/galleries/bulk-destroy', [GalleryController::class, 'bulkDestroy'])
    ->middleware('active_permission:gallery.delete')
    ->name('publication.galleries.bulk-destroy');
Route::post('/publication/galleries/bulk-restore', [GalleryController::class, 'bulkRestore'])
    ->middleware('active_permission:gallery.update|gallery.delete')
    ->name('publication.galleries.bulk-restore');
Route::post('/publication/galleries/bulk-force-destroy', [GalleryController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:gallery.update|gallery.delete')
    ->name('publication.galleries.bulk-force-destroy');
Route::post('/publication/galleries/{id}/restore', [GalleryController::class, 'restore'])
    ->middleware('active_permission:gallery.update|gallery.delete')
    ->name('publication.galleries.restore');
Route::delete('/publication/galleries/{id}/force', [GalleryController::class, 'forceDestroy'])
    ->middleware('active_permission:gallery.update|gallery.delete')
    ->name('publication.galleries.force-destroy');
Route::post('/publication/galleries/{gallery}/toggle', [GalleryController::class, 'toggle'])
    ->middleware('active_permission:gallery.update')
    ->name('publication.galleries.toggle');
Route::get('/publication/galleries', [GalleryController::class, 'index'])
    ->middleware('active_permission:gallery.viewAny')
    ->name('publication.galleries.index');
Route::get('/publication/galleries/create', [GalleryController::class, 'create'])
    ->middleware('active_permission:gallery.create')
    ->name('publication.galleries.create');
Route::post('/publication/galleries', [GalleryController::class, 'store'])
    ->middleware('active_permission:gallery.create')
    ->name('publication.galleries.store');
Route::post('/publication/galleries/{gallery}/images', [GalleryController::class, 'storeImages'])
    ->middleware('active_permission:gallery.update')
    ->name('publication.galleries.images.store');
Route::get('/publication/galleries/{gallery}/edit', [GalleryController::class, 'edit'])
    ->middleware('active_permission:gallery.update')
    ->name('publication.galleries.edit');
Route::get('/publication/galleries/{gallery}', [GalleryController::class, 'show'])
    ->middleware('active_permission:gallery.view')
    ->name('publication.galleries.show');
Route::put('/publication/galleries/{gallery}', [GalleryController::class, 'update'])
    ->middleware('active_permission:gallery.update')
    ->name('publication.galleries.update');
Route::delete('/publication/galleries/{gallery}', [GalleryController::class, 'destroy'])
    ->middleware('active_permission:gallery.delete')
    ->name('publication.galleries.destroy');
Route::patch('/publication/gallery-images/{image}', [GalleryController::class, 'updateImage'])
    ->middleware('active_permission:gallery.update')
    ->name('publication.gallery-images.update');
Route::delete('/publication/gallery-images/{image}', [GalleryController::class, 'destroyImage'])
    ->middleware('active_permission:gallery.delete')
    ->name('publication.gallery-images.destroy');
Route::post('/publication/gallery-images/{image}/cover', [GalleryController::class, 'setCover'])
    ->middleware('active_permission:gallery.update')
    ->name('publication.gallery-images.cover');

// CRUD Lokasi Kampus (Inertia React) — Batch-C1, nama route & middleware paritas Livewire.
Route::get('/campus/campus-locations/export', [CampusLocationController::class, 'export'])
    ->middleware('active_permission:campus-location.viewAny')
    ->name('campus.locations.export');
Route::get('/campus/campus-locations/import/template', [CampusLocationController::class, 'importTemplate'])
    ->middleware('active_permission:campus-location.create')
    ->name('campus.locations.import-template');
Route::post('/campus/campus-locations/import', [CampusLocationController::class, 'import'])
    ->middleware('active_permission:campus-location.create')
    ->name('campus.locations.import');
Route::post('/campus/campus-locations/bulk-destroy', [CampusLocationController::class, 'bulkDestroy'])
    ->middleware('active_permission:campus-location.delete')
    ->name('campus.locations.bulk-destroy');
Route::post('/campus/campus-locations/bulk-restore', [CampusLocationController::class, 'bulkRestore'])
    ->middleware('active_permission:campus-location.update|campus-location.delete')
    ->name('campus.locations.bulk-restore');
Route::post('/campus/campus-locations/bulk-force-destroy', [CampusLocationController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:campus-location.update|campus-location.delete')
    ->name('campus.locations.bulk-force-destroy');
Route::post('/campus/campus-locations/{id}/restore', [CampusLocationController::class, 'restore'])
    ->middleware('active_permission:campus-location.update|campus-location.delete')
    ->name('campus.locations.restore');
Route::delete('/campus/campus-locations/{id}/force', [CampusLocationController::class, 'forceDestroy'])
    ->middleware('active_permission:campus-location.update|campus-location.delete')
    ->name('campus.locations.force-destroy');
Route::post('/campus/campus-locations/{location}/toggle', [CampusLocationController::class, 'toggle'])
    ->middleware('active_permission:campus-location.update')
    ->name('campus.locations.toggle');
Route::post('/campus/campus-locations/{location}/main', [CampusLocationController::class, 'setMain'])
    ->middleware('active_permission:campus-location.update')
    ->name('campus.locations.main');
Route::get('/campus/campus-locations', [CampusLocationController::class, 'index'])
    ->middleware('active_permission:campus-location.viewAny')
    ->name('campus.locations.index');
Route::get('/campus/campus-locations/create', [CampusLocationController::class, 'create'])
    ->middleware('active_permission:campus-location.create')
    ->name('campus.locations.create');
Route::post('/campus/campus-locations', [CampusLocationController::class, 'store'])
    ->middleware('active_permission:campus-location.create')
    ->name('campus.locations.store');
Route::get('/campus/campus-locations/{location}/edit', [CampusLocationController::class, 'edit'])
    ->middleware('active_permission:campus-location.update')
    ->name('campus.locations.edit');
Route::put('/campus/campus-locations/{location}', [CampusLocationController::class, 'update'])
    ->middleware('active_permission:campus-location.update')
    ->name('campus.locations.update');
Route::delete('/campus/campus-locations/{location}', [CampusLocationController::class, 'destroy'])
    ->middleware('active_permission:campus-location.delete')
    ->name('campus.locations.destroy');

// CRUD Gedung (Inertia React) — Batch-C1, nama route & middleware paritas Livewire.
Route::get('/campus/buildings/export', [BuildingController::class, 'export'])
    ->middleware('active_permission:building.viewAny')
    ->name('campus.buildings.export');
Route::get('/campus/buildings/import/template', [BuildingController::class, 'importTemplate'])
    ->middleware('active_permission:building.create')
    ->name('campus.buildings.import-template');
Route::post('/campus/buildings/import', [BuildingController::class, 'import'])
    ->middleware('active_permission:building.create')
    ->name('campus.buildings.import');
Route::post('/campus/buildings/bulk-destroy', [BuildingController::class, 'bulkDestroy'])
    ->middleware('active_permission:building.delete')
    ->name('campus.buildings.bulk-destroy');
Route::post('/campus/buildings/bulk-restore', [BuildingController::class, 'bulkRestore'])
    ->middleware('active_permission:building.update|building.delete')
    ->name('campus.buildings.bulk-restore');
Route::post('/campus/buildings/bulk-force-destroy', [BuildingController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:building.update|building.delete')
    ->name('campus.buildings.bulk-force-destroy');
Route::post('/campus/buildings/{id}/restore', [BuildingController::class, 'restore'])
    ->middleware('active_permission:building.update|building.delete')
    ->name('campus.buildings.restore');
Route::delete('/campus/buildings/{id}/force', [BuildingController::class, 'forceDestroy'])
    ->middleware('active_permission:building.update|building.delete')
    ->name('campus.buildings.force-destroy');
Route::post('/campus/buildings/{building}/toggle', [BuildingController::class, 'toggle'])
    ->middleware('active_permission:building.update')
    ->name('campus.buildings.toggle');
Route::get('/campus/buildings', [BuildingController::class, 'index'])
    ->middleware('active_permission:building.viewAny')
    ->name('campus.buildings.index');
Route::get('/campus/buildings/create', [BuildingController::class, 'create'])
    ->middleware('active_permission:building.create')
    ->name('campus.buildings.create');
Route::post('/campus/buildings', [BuildingController::class, 'store'])
    ->middleware('active_permission:building.create')
    ->name('campus.buildings.store');
Route::get('/campus/buildings/{building}/edit', [BuildingController::class, 'edit'])
    ->middleware('active_permission:building.update')
    ->name('campus.buildings.edit');
Route::put('/campus/buildings/{building}', [BuildingController::class, 'update'])
    ->middleware('active_permission:building.update')
    ->name('campus.buildings.update');
Route::delete('/campus/buildings/{building}', [BuildingController::class, 'destroy'])
    ->middleware('active_permission:building.delete')
    ->name('campus.buildings.destroy');

// CRUD Ruangan (Inertia React) — Batch-C2, nama route & middleware paritas Livewire.
Route::get('/campus/rooms/export', [RoomController::class, 'export'])
    ->middleware('active_permission:room.viewAny')
    ->name('campus.rooms.export');
Route::get('/campus/rooms/import/template', [RoomController::class, 'importTemplate'])
    ->middleware('active_permission:room.create')
    ->name('campus.rooms.import-template');
Route::post('/campus/rooms/import', [RoomController::class, 'import'])
    ->middleware('active_permission:room.create')
    ->name('campus.rooms.import');
Route::post('/campus/rooms/bulk-destroy', [RoomController::class, 'bulkDestroy'])
    ->middleware('active_permission:room.delete')
    ->name('campus.rooms.bulk-destroy');
Route::post('/campus/rooms/bulk-restore', [RoomController::class, 'bulkRestore'])
    ->middleware('active_permission:room.update|room.delete')
    ->name('campus.rooms.bulk-restore');
Route::post('/campus/rooms/bulk-force-destroy', [RoomController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:room.update|room.delete')
    ->name('campus.rooms.bulk-force-destroy');
Route::post('/campus/rooms/{id}/restore', [RoomController::class, 'restore'])
    ->middleware('active_permission:room.update|room.delete')
    ->name('campus.rooms.restore');
Route::delete('/campus/rooms/{id}/force', [RoomController::class, 'forceDestroy'])
    ->middleware('active_permission:room.update|room.delete')
    ->name('campus.rooms.force-destroy');
Route::post('/campus/rooms/{room}/toggle', [RoomController::class, 'toggle'])
    ->middleware('active_permission:room.update')
    ->name('campus.rooms.toggle');
Route::get('/campus/rooms', [RoomController::class, 'index'])
    ->middleware('active_permission:room.viewAny')
    ->name('campus.rooms.index');
Route::get('/campus/rooms/create', [RoomController::class, 'create'])
    ->middleware('active_permission:room.create')
    ->name('campus.rooms.create');
Route::post('/campus/rooms', [RoomController::class, 'store'])
    ->middleware('active_permission:room.create')
    ->name('campus.rooms.store');
Route::get('/campus/rooms/{room}/edit', [RoomController::class, 'edit'])
    ->middleware('active_permission:room.update')
    ->name('campus.rooms.edit');
Route::put('/campus/rooms/{room}', [RoomController::class, 'update'])
    ->middleware('active_permission:room.update')
    ->name('campus.rooms.update');
Route::delete('/campus/rooms/{room}', [RoomController::class, 'destroy'])
    ->middleware('active_permission:room.delete')
    ->name('campus.rooms.destroy');

// CRUD Aset Kampus (Inertia React) — Batch-C2, nama route & middleware paritas Livewire.
Route::get('/campus/campus-assets/export', [CampusAssetController::class, 'export'])
    ->middleware('active_permission:campus-asset.viewAny')
    ->name('campus.assets.export');
Route::get('/campus/campus-assets/import/template', [CampusAssetController::class, 'importTemplate'])
    ->middleware('active_permission:campus-asset.create')
    ->name('campus.assets.import-template');
Route::post('/campus/campus-assets/import', [CampusAssetController::class, 'import'])
    ->middleware('active_permission:campus-asset.create')
    ->name('campus.assets.import');
Route::post('/campus/campus-assets/bulk-destroy', [CampusAssetController::class, 'bulkDestroy'])
    ->middleware('active_permission:campus-asset.delete')
    ->name('campus.assets.bulk-destroy');
Route::post('/campus/campus-assets/bulk-restore', [CampusAssetController::class, 'bulkRestore'])
    ->middleware('active_permission:campus-asset.update|campus-asset.delete')
    ->name('campus.assets.bulk-restore');
Route::post('/campus/campus-assets/bulk-force-destroy', [CampusAssetController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:campus-asset.update|campus-asset.delete')
    ->name('campus.assets.bulk-force-destroy');
Route::post('/campus/campus-assets/{id}/restore', [CampusAssetController::class, 'restore'])
    ->middleware('active_permission:campus-asset.update|campus-asset.delete')
    ->name('campus.assets.restore');
Route::delete('/campus/campus-assets/{id}/force', [CampusAssetController::class, 'forceDestroy'])
    ->middleware('active_permission:campus-asset.update|campus-asset.delete')
    ->name('campus.assets.force-destroy');
Route::get('/campus/campus-assets', [CampusAssetController::class, 'index'])
    ->middleware('active_permission:campus-asset.viewAny')
    ->name('campus.assets.index');
Route::get('/campus/campus-assets/create', [CampusAssetController::class, 'create'])
    ->middleware('active_permission:campus-asset.create')
    ->name('campus.assets.create');
Route::post('/campus/campus-assets', [CampusAssetController::class, 'store'])
    ->middleware('active_permission:campus-asset.create')
    ->name('campus.assets.store');
Route::get('/campus/campus-assets/bulk-create', [CampusAssetController::class, 'bulkCreate'])
    ->middleware('active_permission:campus-asset.create')
    ->name('campus.assets.bulk-create');
Route::post('/campus/campus-assets/bulk-store', [CampusAssetController::class, 'bulkStore'])
    ->middleware('active_permission:campus-asset.create')
    ->name('campus.assets.bulk-store');
Route::get('/campus/products', [CampusAssetController::class, 'showProduct'])
    ->middleware('active_permission:campus-asset.view')
    ->name('campus.products.show');
Route::get('/campus/campus-assets/{asset}/edit', [CampusAssetController::class, 'edit'])
    ->middleware('active_permission:campus-asset.update')
    ->name('campus.assets.edit');
Route::get('/campus/campus-assets/{asset}', [CampusAssetController::class, 'show'])
    ->middleware('active_permission:campus-asset.view')
    ->name('campus.assets.show');
Route::put('/campus/campus-assets/{asset}', [CampusAssetController::class, 'update'])
    ->middleware('active_permission:campus-asset.update')
    ->name('campus.assets.update');
Route::delete('/campus/campus-assets/{asset}', [CampusAssetController::class, 'destroy'])
    ->middleware('active_permission:campus-asset.delete')
    ->name('campus.assets.destroy');
Route::post('/campus/campus-assets/{asset}/report-damage', [CampusAssetController::class, 'reportDamage'])
    ->middleware('active_permission:campus-asset.update')
    ->name('campus.assets.report-damage');

// CRUD Peminjaman Ruangan (Inertia React) — Batch-C3, state machine approve.
Route::get('/campus/room-reservations/export', [RoomReservationController::class, 'export'])
    ->middleware('active_permission:room-reservation.viewAny')
    ->name('campus.reservations.export');
Route::post('/campus/room-reservations/bulk-destroy', [RoomReservationController::class, 'bulkDestroy'])
    ->middleware('active_permission:room-reservation.delete')
    ->name('campus.reservations.bulk-destroy');
Route::post('/campus/room-reservations/bulk-restore', [RoomReservationController::class, 'bulkRestore'])
    ->middleware('active_permission:room-reservation.update|room-reservation.delete')
    ->name('campus.reservations.bulk-restore');
Route::post('/campus/room-reservations/bulk-force-destroy', [RoomReservationController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:room-reservation.update|room-reservation.delete')
    ->name('campus.reservations.bulk-force-destroy');
Route::post('/campus/room-reservations/{id}/restore', [RoomReservationController::class, 'restore'])
    ->middleware('active_permission:room-reservation.update|room-reservation.delete')
    ->name('campus.reservations.restore');
Route::delete('/campus/room-reservations/{id}/force', [RoomReservationController::class, 'forceDestroy'])
    ->middleware('active_permission:room-reservation.update|room-reservation.delete')
    ->name('campus.reservations.force-destroy');
Route::get('/campus/room-reservations/search-users', [RoomReservationController::class, 'searchUsers'])
    ->middleware('active_permission:room-reservation.create|room-reservation.update')
    ->name('campus.reservations.search-users');
Route::post('/campus/room-reservations/{reservation}/approve', [RoomReservationController::class, 'approve'])
    ->middleware('active_permission:room-reservation.approve')
    ->name('campus.reservations.approve');
Route::post('/campus/room-reservations/{reservation}/reject', [RoomReservationController::class, 'reject'])
    ->middleware('active_permission:room-reservation.approve')
    ->name('campus.reservations.reject');
Route::post('/campus/room-reservations/{reservation}/cancel', [RoomReservationController::class, 'cancel'])
    ->middleware('active_permission:room-reservation.update')
    ->name('campus.reservations.cancel');
Route::get('/campus/room-reservations', [RoomReservationController::class, 'index'])
    ->middleware('active_permission:room-reservation.viewAny')
    ->name('campus.reservations.index');
Route::get('/campus/room-reservations/create', [RoomReservationController::class, 'create'])
    ->middleware('active_permission:room-reservation.create')
    ->name('campus.reservations.create');
Route::post('/campus/room-reservations', [RoomReservationController::class, 'store'])
    ->middleware('active_permission:room-reservation.create')
    ->name('campus.reservations.store');
Route::get('/campus/room-reservations/{reservation}/edit', [RoomReservationController::class, 'edit'])
    ->middleware('active_permission:room-reservation.update')
    ->name('campus.reservations.edit');
Route::get('/campus/room-reservations/{reservation}', [RoomReservationController::class, 'show'])
    ->middleware('active_permission:room-reservation.view')
    ->name('campus.reservations.show');
Route::put('/campus/room-reservations/{reservation}', [RoomReservationController::class, 'update'])
    ->middleware('active_permission:room-reservation.update')
    ->name('campus.reservations.update');
Route::delete('/campus/room-reservations/{reservation}', [RoomReservationController::class, 'destroy'])
    ->middleware('active_permission:room-reservation.delete')
    ->name('campus.reservations.destroy');

// CRUD Laporan Kerusakan (Inertia React) — Batch-C4, lifecycle resolve.
Route::get('/campus/facility-maintenances/export', [MaintenanceController::class, 'export'])
    ->middleware('active_permission:facility-maintenance.viewAny')
    ->name('campus.maintenance.export');
Route::post('/campus/facility-maintenances/bulk-destroy', [MaintenanceController::class, 'bulkDestroy'])
    ->middleware('active_permission:facility-maintenance.delete')
    ->name('campus.maintenance.bulk-destroy');
Route::post('/campus/facility-maintenances/bulk-restore', [MaintenanceController::class, 'bulkRestore'])
    ->middleware('active_permission:facility-maintenance.update|facility-maintenance.delete')
    ->name('campus.maintenance.bulk-restore');
Route::post('/campus/facility-maintenances/bulk-force-destroy', [MaintenanceController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:facility-maintenance.update|facility-maintenance.delete')
    ->name('campus.maintenance.bulk-force-destroy');
Route::post('/campus/facility-maintenances/{id}/restore', [MaintenanceController::class, 'restore'])
    ->middleware('active_permission:facility-maintenance.update|facility-maintenance.delete')
    ->name('campus.maintenance.restore');
Route::delete('/campus/facility-maintenances/{id}/force', [MaintenanceController::class, 'forceDestroy'])
    ->middleware('active_permission:facility-maintenance.update|facility-maintenance.delete')
    ->name('campus.maintenance.force-destroy');
Route::get('/campus/facility-maintenances/search-users', [MaintenanceController::class, 'searchUsers'])
    ->middleware('active_permission:facility-maintenance.create|facility-maintenance.update')
    ->name('campus.maintenance.search-users');
Route::get('/campus/facility-maintenances/search-rooms', [MaintenanceController::class, 'searchRooms'])
    ->middleware('active_permission:facility-maintenance.create|facility-maintenance.update')
    ->name('campus.maintenance.search-rooms');
Route::get('/campus/facility-maintenances/search-assets', [MaintenanceController::class, 'searchAssets'])
    ->middleware('active_permission:facility-maintenance.create|facility-maintenance.update')
    ->name('campus.maintenance.search-assets');
Route::post('/campus/facility-maintenances/{ticket}/resolve', [MaintenanceController::class, 'resolve'])
    ->middleware('active_permission:facility-maintenance.resolve')
    ->name('campus.maintenance.resolve');
Route::get('/campus/facility-maintenances', [MaintenanceController::class, 'index'])
    ->middleware('active_permission:facility-maintenance.viewAny')
    ->name('campus.maintenance.index');
Route::get('/campus/facility-maintenances/create', [MaintenanceController::class, 'create'])
    ->middleware('active_permission:facility-maintenance.create')
    ->name('campus.maintenance.create');
Route::post('/campus/facility-maintenances', [MaintenanceController::class, 'store'])
    ->middleware('active_permission:facility-maintenance.create')
    ->name('campus.maintenance.store');
Route::get('/campus/facility-maintenances/{ticket}/edit', [MaintenanceController::class, 'edit'])
    ->middleware('active_permission:facility-maintenance.update')
    ->name('campus.maintenance.edit');
Route::get('/campus/facility-maintenances/{ticket}', [MaintenanceController::class, 'show'])
    ->middleware('active_permission:facility-maintenance.view')
    ->name('campus.maintenance.show');
Route::put('/campus/facility-maintenances/{ticket}', [MaintenanceController::class, 'update'])
    ->middleware('active_permission:facility-maintenance.update')
    ->name('campus.maintenance.update');
Route::delete('/campus/facility-maintenances/{ticket}', [MaintenanceController::class, 'destroy'])
    ->middleware('active_permission:facility-maintenance.delete')
    ->name('campus.maintenance.destroy');

// CRUD Kategori Aset (Inertia React) — nama route & middleware paritas Livewire.
Route::get('/campus/asset-categories/export', [AssetCategoryController::class, 'export'])
    ->middleware('active_permission:asset-category.viewAny')
    ->name('campus.asset-categories.export');
Route::get('/campus/asset-categories/import/template', [AssetCategoryController::class, 'importTemplate'])
    ->middleware('active_permission:asset-category.create')
    ->name('campus.asset-categories.import-template');
Route::post('/campus/asset-categories/import', [AssetCategoryController::class, 'import'])
    ->middleware('active_permission:asset-category.create')
    ->name('campus.asset-categories.import');
Route::post('/campus/asset-categories/bulk-destroy', [AssetCategoryController::class, 'bulkDestroy'])
    ->middleware('active_permission:asset-category.delete')
    ->name('campus.asset-categories.bulk-destroy');
Route::post('/campus/asset-categories/bulk-restore', [AssetCategoryController::class, 'bulkRestore'])
    ->middleware('active_permission:asset-category.update|asset-category.delete')
    ->name('campus.asset-categories.bulk-restore');
Route::post('/campus/asset-categories/bulk-force-destroy', [AssetCategoryController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:asset-category.update|asset-category.delete')
    ->name('campus.asset-categories.bulk-force-destroy');
Route::post('/campus/asset-categories/{id}/restore', [AssetCategoryController::class, 'restore'])
    ->middleware('active_permission:asset-category.update|asset-category.delete')
    ->name('campus.asset-categories.restore');
Route::delete('/campus/asset-categories/{id}/force', [AssetCategoryController::class, 'forceDestroy'])
    ->middleware('active_permission:asset-category.update|asset-category.delete')
    ->name('campus.asset-categories.force-destroy');
Route::post('/campus/asset-categories/{category}/toggle', [AssetCategoryController::class, 'toggle'])
    ->middleware('active_permission:asset-category.update')
    ->name('campus.asset-categories.toggle');
Route::get('/campus/asset-categories', [AssetCategoryController::class, 'index'])
    ->middleware('active_permission:asset-category.viewAny')
    ->name('campus.asset-categories.index');
Route::get('/campus/asset-categories/create', [AssetCategoryController::class, 'create'])
    ->middleware('active_permission:asset-category.create')
    ->name('campus.asset-categories.create');
Route::post('/campus/asset-categories', [AssetCategoryController::class, 'store'])
    ->middleware('active_permission:asset-category.create')
    ->name('campus.asset-categories.store');
Route::get('/campus/asset-categories/{category}/edit', [AssetCategoryController::class, 'edit'])
    ->middleware('active_permission:asset-category.update')
    ->name('campus.asset-categories.edit');
Route::put('/campus/asset-categories/{category}', [AssetCategoryController::class, 'update'])
    ->middleware('active_permission:asset-category.update')
    ->name('campus.asset-categories.update');
Route::delete('/campus/asset-categories/{category}', [AssetCategoryController::class, 'destroy'])
    ->middleware('active_permission:asset-category.delete')
    ->name('campus.asset-categories.destroy');

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

Route::get('/financial/dashboard', [AdminDashboardController::class, 'financialDashboard'])
    ->middleware('active_permission:financial-dashboard.viewAny')
    ->name('financial.dashboard.index');
Route::get('/student-services/dashboard', [AdminDashboardController::class, 'studentServicesDashboard'])
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
Route::get('/campus/{resource}/export/pdf', [AdminCampusExportController::class, 'pdf'])
    ->name('campus.exports.pdf');
Route::get('/campus/{resource}/imports/template', [AdminCampusExportController::class, 'importTemplate'])
    ->name('campus.import-template');

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

// CRUD Periode & Gelombang PMB (Inertia React) — Batch AD-1.
Route::get('/admission/admission-periods/export', [AdmissionPeriodController::class, 'export'])
    ->middleware('active_permission:admission-period.viewAny')
    ->name('admission.admission-periods.export');
Route::post('/admission/admission-periods/bulk-destroy', [AdmissionPeriodController::class, 'bulkDestroy'])
    ->middleware('active_permission:admission-period.delete')
    ->name('admission.admission-periods.bulk-destroy');
Route::post('/admission/admission-periods/bulk-restore', [AdmissionPeriodController::class, 'bulkRestore'])
    ->middleware('active_permission:admission-period.update|admission-period.delete')
    ->name('admission.admission-periods.bulk-restore');
Route::post('/admission/admission-periods/bulk-force-destroy', [AdmissionPeriodController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:admission-period.update|admission-period.delete')
    ->name('admission.admission-periods.bulk-force-destroy');
Route::post('/admission/admission-periods/{id}/restore', [AdmissionPeriodController::class, 'restore'])
    ->middleware('active_permission:admission-period.update|admission-period.delete')
    ->name('admission.admission-periods.restore');
Route::delete('/admission/admission-periods/{id}/force', [AdmissionPeriodController::class, 'forceDestroy'])
    ->middleware('active_permission:admission-period.update|admission-period.delete')
    ->name('admission.admission-periods.force-destroy');
Route::post('/admission/admission-periods/{period}/toggle', [AdmissionPeriodController::class, 'toggle'])
    ->middleware('active_permission:admission-period.update')
    ->name('admission.admission-periods.toggle');
Route::get('/admission/admission-periods', [AdmissionPeriodController::class, 'index'])
    ->middleware('active_permission:admission-period.viewAny')
    ->name('admission.admission-periods.index');
Route::get('/admission/admission-periods/create', [AdmissionPeriodController::class, 'create'])
    ->middleware('active_permission:admission-period.create')
    ->name('admission.admission-periods.create');
Route::post('/admission/admission-periods', [AdmissionPeriodController::class, 'store'])
    ->middleware('active_permission:admission-period.create')
    ->name('admission.admission-periods.store');
Route::get('/admission/admission-periods/{period}/edit', [AdmissionPeriodController::class, 'edit'])
    ->middleware('active_permission:admission-period.update')
    ->name('admission.admission-periods.edit');
Route::put('/admission/admission-periods/{period}', [AdmissionPeriodController::class, 'update'])
    ->middleware('active_permission:admission-period.update')
    ->name('admission.admission-periods.update');
Route::delete('/admission/admission-periods/{period}', [AdmissionPeriodController::class, 'destroy'])
    ->middleware('active_permission:admission-period.delete')
    ->name('admission.admission-periods.destroy');

// CRUD Kuota Penerimaan PMB (Inertia React) — Batch AD-1 (tanpa soft delete).
Route::get('/admission/admission-quotas/export', [AdmissionQuotaController::class, 'export'])
    ->middleware('active_permission:admission-quota.viewAny')
    ->name('admission.admission-quotas.export');
Route::post('/admission/admission-quotas/bulk-destroy', [AdmissionQuotaController::class, 'bulkDestroy'])
    ->middleware('active_permission:admission-quota.delete')
    ->name('admission.admission-quotas.bulk-destroy');
Route::get('/admission/admission-quotas', [AdmissionQuotaController::class, 'index'])
    ->middleware('active_permission:admission-quota.viewAny')
    ->name('admission.admission-quotas.index');
Route::get('/admission/admission-quotas/create', [AdmissionQuotaController::class, 'create'])
    ->middleware('active_permission:admission-quota.create')
    ->name('admission.admission-quotas.create');
Route::post('/admission/admission-quotas', [AdmissionQuotaController::class, 'store'])
    ->middleware('active_permission:admission-quota.create')
    ->name('admission.admission-quotas.store');
Route::get('/admission/admission-quotas/{quota}/edit', [AdmissionQuotaController::class, 'edit'])
    ->middleware('active_permission:admission-quota.update')
    ->name('admission.admission-quotas.edit');
Route::put('/admission/admission-quotas/{quota}', [AdmissionQuotaController::class, 'update'])
    ->middleware('active_permission:admission-quota.update')
    ->name('admission.admission-quotas.update');
Route::delete('/admission/admission-quotas/{quota}', [AdmissionQuotaController::class, 'destroy'])
    ->middleware('active_permission:admission-quota.delete')
    ->name('admission.admission-quotas.destroy');

// CRUD Aturan Penomoran NIM (Inertia React) — Batch AD-1 (tanpa soft delete).
Route::get('/admission/nim-generation-rules/export', [NimGenerationRuleController::class, 'export'])
    ->middleware('active_permission:nim-generation-rule.viewAny')
    ->name('admission.nim-generation-rules.export');
Route::post('/admission/nim-generation-rules/bulk-destroy', [NimGenerationRuleController::class, 'bulkDestroy'])
    ->middleware('active_permission:nim-generation-rule.delete')
    ->name('admission.nim-generation-rules.bulk-destroy');
Route::post('/admission/nim-generation-rules/{rule}/toggle', [NimGenerationRuleController::class, 'toggle'])
    ->middleware('active_permission:nim-generation-rule.update')
    ->name('admission.nim-generation-rules.toggle');
Route::get('/admission/nim-generation-rules', [NimGenerationRuleController::class, 'index'])
    ->middleware('active_permission:nim-generation-rule.viewAny')
    ->name('admission.nim-generation-rules.index');
Route::get('/admission/nim-generation-rules/create', [NimGenerationRuleController::class, 'create'])
    ->middleware('active_permission:nim-generation-rule.create')
    ->name('admission.nim-generation-rules.create');
Route::post('/admission/nim-generation-rules', [NimGenerationRuleController::class, 'store'])
    ->middleware('active_permission:nim-generation-rule.create')
    ->name('admission.nim-generation-rules.store');
Route::get('/admission/nim-generation-rules/{rule}/edit', [NimGenerationRuleController::class, 'edit'])
    ->middleware('active_permission:nim-generation-rule.update')
    ->name('admission.nim-generation-rules.edit');
Route::put('/admission/nim-generation-rules/{rule}', [NimGenerationRuleController::class, 'update'])
    ->middleware('active_permission:nim-generation-rule.update')
    ->name('admission.nim-generation-rules.update');
Route::delete('/admission/nim-generation-rules/{rule}', [NimGenerationRuleController::class, 'destroy'])
    ->middleware('active_permission:nim-generation-rule.delete')
    ->name('admission.nim-generation-rules.destroy');

// CRUD Jadwal Ujian & Seleksi PMB (Inertia React) — Batch AD-2.
Route::get('/admission/admission-exam-schedules/export', [AdmissionExamScheduleController::class, 'export'])
    ->middleware('active_permission:admission-exam-schedule.viewAny')
    ->name('admission.admission-exam-schedules.export');
Route::post('/admission/admission-exam-schedules/bulk-destroy', [AdmissionExamScheduleController::class, 'bulkDestroy'])
    ->middleware('active_permission:admission-exam-schedule.delete')
    ->name('admission.admission-exam-schedules.bulk-destroy');
Route::post('/admission/admission-exam-schedules/bulk-restore', [AdmissionExamScheduleController::class, 'bulkRestore'])
    ->middleware('active_permission:admission-exam-schedule.update|admission-exam-schedule.delete')
    ->name('admission.admission-exam-schedules.bulk-restore');
Route::post('/admission/admission-exam-schedules/bulk-force-destroy', [AdmissionExamScheduleController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:admission-exam-schedule.update|admission-exam-schedule.delete')
    ->name('admission.admission-exam-schedules.bulk-force-destroy');
Route::post('/admission/admission-exam-schedules/{id}/restore', [AdmissionExamScheduleController::class, 'restore'])
    ->middleware('active_permission:admission-exam-schedule.update|admission-exam-schedule.delete')
    ->name('admission.admission-exam-schedules.restore');
Route::delete('/admission/admission-exam-schedules/{id}/force', [AdmissionExamScheduleController::class, 'forceDestroy'])
    ->middleware('active_permission:admission-exam-schedule.update|admission-exam-schedule.delete')
    ->name('admission.admission-exam-schedules.force-destroy');
Route::post('/admission/admission-exam-schedules/{schedule}/toggle', [AdmissionExamScheduleController::class, 'toggle'])
    ->middleware('active_permission:admission-exam-schedule.update')
    ->name('admission.admission-exam-schedules.toggle');
Route::get('/admission/admission-exam-schedules', [AdmissionExamScheduleController::class, 'index'])
    ->middleware('active_permission:admission-exam-schedule.viewAny')
    ->name('admission.admission-exam-schedules.index');
Route::get('/admission/admission-exam-schedules/create', [AdmissionExamScheduleController::class, 'create'])
    ->middleware('active_permission:admission-exam-schedule.create')
    ->name('admission.admission-exam-schedules.create');
Route::post('/admission/admission-exam-schedules', [AdmissionExamScheduleController::class, 'store'])
    ->middleware('active_permission:admission-exam-schedule.create')
    ->name('admission.admission-exam-schedules.store');
Route::get('/admission/admission-exam-schedules/{schedule}/edit', [AdmissionExamScheduleController::class, 'edit'])
    ->middleware('active_permission:admission-exam-schedule.update')
    ->name('admission.admission-exam-schedules.edit');
Route::put('/admission/admission-exam-schedules/{schedule}', [AdmissionExamScheduleController::class, 'update'])
    ->middleware('active_permission:admission-exam-schedule.update')
    ->name('admission.admission-exam-schedules.update');
Route::delete('/admission/admission-exam-schedules/{schedule}', [AdmissionExamScheduleController::class, 'destroy'])
    ->middleware('active_permission:admission-exam-schedule.delete')
    ->name('admission.admission-exam-schedules.destroy');
Route::post('/admission/admission-exam-schedules/{schedule}/participants', [AdmissionExamScheduleController::class, 'assignParticipant'])
    ->middleware('active_permission:admission-exam-schedule.update')
    ->name('admission.admission-exam-schedules.participants.store');
Route::post('/admission/admission-exam-schedules/{schedule}/participants/assign-all', [AdmissionExamScheduleController::class, 'assignAllFromPeriod'])
    ->middleware('active_permission:admission-exam-schedule.update')
    ->name('admission.admission-exam-schedules.participants.assign-all');
Route::patch('/admission/admission-exam-schedules/{schedule}/participants/{participant}/attendance', [AdmissionExamScheduleController::class, 'updateAttendance'])
    ->middleware('active_permission:admission-exam-schedule.update')
    ->name('admission.admission-exam-schedules.participants.attendance');
Route::post('/admission/admission-exam-schedules/{schedule}/participants/{participant}/score', [AdmissionExamScheduleController::class, 'saveScore'])
    ->middleware('active_permission:admission-exam-schedule.update')
    ->name('admission.admission-exam-schedules.participants.score');
Route::delete('/admission/admission-exam-schedules/{schedule}/participants/{participant}', [AdmissionExamScheduleController::class, 'removeParticipant'])
    ->middleware('active_permission:admission-exam-schedule.update')
    ->name('admission.admission-exam-schedules.participants.destroy');
Route::get('/admission/admission-exam-schedules/{schedule}', [AdmissionExamScheduleController::class, 'show'])
    ->middleware('active_permission:admission-exam-schedule.view')
    ->name('admission.admission-exam-schedules.show');

// Pendaftar PMB (Inertia React) — Batch AD-2.
Route::get('/admission/admission-applications/export', [AdmissionApplicationController::class, 'export'])
    ->middleware('active_permission:admission-application.viewAny')
    ->name('admission.admission-applications.export');
Route::post('/admission/admission-applications/bulk-destroy', [AdmissionApplicationController::class, 'bulkDestroy'])
    ->middleware('active_permission:admission-application.delete')
    ->name('admission.admission-applications.bulk-destroy');
Route::post('/admission/admission-applications/bulk-restore', [AdmissionApplicationController::class, 'bulkRestore'])
    ->middleware('active_permission:admission-application.update|admission-application.delete')
    ->name('admission.admission-applications.bulk-restore');
Route::post('/admission/admission-applications/bulk-force-destroy', [AdmissionApplicationController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:admission-application.update|admission-application.delete')
    ->name('admission.admission-applications.bulk-force-destroy');
Route::post('/admission/admission-applications/bulk-convert', [AdmissionApplicationController::class, 'bulkConvert'])
    ->middleware('active_permission:admission-application.update')
    ->name('admission.admission-applications.bulk-convert');
Route::post('/admission/admission-applications/{id}/restore', [AdmissionApplicationController::class, 'restore'])
    ->middleware('active_permission:admission-application.update|admission-application.delete')
    ->name('admission.admission-applications.restore');
Route::delete('/admission/admission-applications/{id}/force', [AdmissionApplicationController::class, 'forceDestroy'])
    ->middleware('active_permission:admission-application.update|admission-application.delete')
    ->name('admission.admission-applications.force-destroy');
Route::get('/admission/admission-applications', [AdmissionApplicationController::class, 'index'])
    ->middleware('active_permission:admission-application.viewAny')
    ->name('admission.admission-applications.index');
Route::get('/admission/admission-applications/{application}', [AdmissionApplicationController::class, 'show'])
    ->middleware('active_permission:admission-application.view')
    ->name('admission.admission-applications.show');
Route::put('/admission/admission-applications/{application}/review', [AdmissionApplicationController::class, 'updateReview'])
    ->middleware('active_permission:admission-application.update')
    ->name('admission.admission-applications.update-review');
Route::patch('/admission/admission-applications/{application}/documents/{document}/verify', [AdmissionApplicationController::class, 'verifyDocument'])
    ->middleware('active_permission:admission-application.update')
    ->name('admission.admission-applications.documents.verify');
Route::post('/admission/admission-applications/{application}/convert', [AdmissionApplicationController::class, 'convert'])
    ->middleware('active_permission:admission-application.update')
    ->name('admission.admission-applications.convert');
Route::delete('/admission/admission-applications/{application}', [AdmissionApplicationController::class, 'destroy'])
    ->middleware('active_permission:admission-application.delete')
    ->name('admission.admission-applications.destroy');

// Perankingan & keputusan seleksi PMB (Inertia React) — Batch AD-2.
Route::get('/admission/admission-selection', [AdmissionSelectionController::class, 'index'])
    ->middleware('active_permission:admission-selection.viewAny')
    ->name('admission.admission-selection.index');
Route::post('/admission/admission-selection/bulk-decision', [AdmissionSelectionController::class, 'bulkDecision'])
    ->middleware('active_permission:admission-selection.update')
    ->name('admission.admission-selection.bulk-decision');
Route::post('/admission/admission-selection/{application}/decision', [AdmissionSelectionController::class, 'updateDecision'])
    ->middleware('active_permission:admission-selection.update')
    ->name('admission.admission-selection.decision');

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

// CRUD Profil Alumni (Inertia React) — Batch AL-1.
Route::get('/alumni/alumni-profiles/export', [AlumniProfileController::class, 'export'])
    ->middleware('active_permission:alumni-profile.viewAny')
    ->name('alumni.alumni-profiles.export');
Route::post('/alumni/alumni-profiles/bulk-destroy', [AlumniProfileController::class, 'bulkDestroy'])
    ->middleware('active_permission:alumni-profile.delete')
    ->name('alumni.alumni-profiles.bulk-destroy');
Route::post('/alumni/alumni-profiles/bulk-restore', [AlumniProfileController::class, 'bulkRestore'])
    ->middleware('active_permission:alumni-profile.update|alumni-profile.delete')
    ->name('alumni.alumni-profiles.bulk-restore');
Route::post('/alumni/alumni-profiles/bulk-force-destroy', [AlumniProfileController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:alumni-profile.update|alumni-profile.delete')
    ->name('alumni.alumni-profiles.bulk-force-destroy');
Route::post('/alumni/alumni-profiles/batch-convert', [AlumniConversionController::class, 'batchConvert'])
    ->middleware('active_permission:alumni-profile.create')
    ->name('alumni.alumni-profiles.batch-convert');
Route::post('/alumni/alumni-profiles/{id}/restore', [AlumniProfileController::class, 'restore'])
    ->middleware('active_permission:alumni-profile.update|alumni-profile.delete')
    ->name('alumni.alumni-profiles.restore');
Route::delete('/alumni/alumni-profiles/{id}/force', [AlumniProfileController::class, 'forceDestroy'])
    ->middleware('active_permission:alumni-profile.update|alumni-profile.delete')
    ->name('alumni.alumni-profiles.force-destroy');
Route::post('/alumni/alumni-profiles/{profile}/toggle', [AlumniProfileController::class, 'toggle'])
    ->middleware('active_permission:alumni-profile.update')
    ->name('alumni.alumni-profiles.toggle');
Route::get('/alumni/alumni-profiles', [AlumniProfileController::class, 'index'])
    ->middleware('active_permission:alumni-profile.viewAny')
    ->name('alumni.alumni-profiles.index');
Route::get('/alumni/alumni-profiles/create', [AlumniProfileController::class, 'create'])
    ->middleware('active_permission:alumni-profile.create')
    ->name('alumni.alumni-profiles.create');
Route::post('/alumni/alumni-profiles', [AlumniProfileController::class, 'store'])
    ->middleware('active_permission:alumni-profile.create')
    ->name('alumni.alumni-profiles.store');
Route::get('/alumni/alumni-profiles/{profile}/edit', [AlumniProfileController::class, 'edit'])
    ->middleware('active_permission:alumni-profile.update')
    ->name('alumni.alumni-profiles.edit');
Route::put('/alumni/alumni-profiles/{profile}', [AlumniProfileController::class, 'update'])
    ->middleware('active_permission:alumni-profile.update')
    ->name('alumni.alumni-profiles.update');
Route::delete('/alumni/alumni-profiles/{profile}', [AlumniProfileController::class, 'destroy'])
    ->middleware('active_permission:alumni-profile.delete')
    ->name('alumni.alumni-profiles.destroy');
Route::get('/alumni/alumni-profiles/{profile}', [AlumniProfileController::class, 'show'])
    ->middleware('active_permission:alumni-profile.view')
    ->name('alumni.alumni-profiles.show');

// CRUD Mitra Perusahaan Alumni (Inertia React) — Batch AL-1.
Route::get('/alumni/employer-partners/export', [EmployerPartnerController::class, 'export'])
    ->middleware('active_permission:employer-partner.viewAny')
    ->name('alumni.employer-partners.export');
Route::post('/alumni/employer-partners/bulk-destroy', [EmployerPartnerController::class, 'bulkDestroy'])
    ->middleware('active_permission:employer-partner.delete')
    ->name('alumni.employer-partners.bulk-destroy');
Route::post('/alumni/employer-partners/bulk-restore', [EmployerPartnerController::class, 'bulkRestore'])
    ->middleware('active_permission:employer-partner.update|employer-partner.delete')
    ->name('alumni.employer-partners.bulk-restore');
Route::post('/alumni/employer-partners/bulk-force-destroy', [EmployerPartnerController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:employer-partner.update|employer-partner.delete')
    ->name('alumni.employer-partners.bulk-force-destroy');
Route::post('/alumni/employer-partners/{id}/restore', [EmployerPartnerController::class, 'restore'])
    ->middleware('active_permission:employer-partner.update|employer-partner.delete')
    ->name('alumni.employer-partners.restore');
Route::delete('/alumni/employer-partners/{id}/force', [EmployerPartnerController::class, 'forceDestroy'])
    ->middleware('active_permission:employer-partner.update|employer-partner.delete')
    ->name('alumni.employer-partners.force-destroy');
Route::post('/alumni/employer-partners/{employerPartner}/toggle', [EmployerPartnerController::class, 'toggle'])
    ->middleware('active_permission:employer-partner.update')
    ->name('alumni.employer-partners.toggle');
Route::get('/alumni/employer-partners', [EmployerPartnerController::class, 'index'])
    ->middleware('active_permission:employer-partner.viewAny')
    ->name('alumni.employer-partners.index');
Route::get('/alumni/employer-partners/create', [EmployerPartnerController::class, 'create'])
    ->middleware('active_permission:employer-partner.create')
    ->name('alumni.employer-partners.create');
Route::post('/alumni/employer-partners', [EmployerPartnerController::class, 'store'])
    ->middleware('active_permission:employer-partner.create')
    ->name('alumni.employer-partners.store');
Route::get('/alumni/employer-partners/{employerPartner}/edit', [EmployerPartnerController::class, 'edit'])
    ->middleware('active_permission:employer-partner.update')
    ->name('alumni.employer-partners.edit');
Route::put('/alumni/employer-partners/{employerPartner}', [EmployerPartnerController::class, 'update'])
    ->middleware('active_permission:employer-partner.update')
    ->name('alumni.employer-partners.update');
Route::delete('/alumni/employer-partners/{employerPartner}', [EmployerPartnerController::class, 'destroy'])
    ->middleware('active_permission:employer-partner.delete')
    ->name('alumni.employer-partners.destroy');

// CRUD Lowongan Kerja Alumni (Inertia React) — Batch AL-1.
Route::get('/alumni/job-postings/export', [JobPostingController::class, 'export'])
    ->middleware('active_permission:job-posting.viewAny')
    ->name('alumni.job-postings.export');
Route::post('/alumni/job-postings/bulk-destroy', [JobPostingController::class, 'bulkDestroy'])
    ->middleware('active_permission:job-posting.delete')
    ->name('alumni.job-postings.bulk-destroy');
Route::post('/alumni/job-postings/bulk-restore', [JobPostingController::class, 'bulkRestore'])
    ->middleware('active_permission:job-posting.update|job-posting.delete')
    ->name('alumni.job-postings.bulk-restore');
Route::post('/alumni/job-postings/bulk-force-destroy', [JobPostingController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:job-posting.update|job-posting.delete')
    ->name('alumni.job-postings.bulk-force-destroy');
Route::post('/alumni/job-postings/{id}/restore', [JobPostingController::class, 'restore'])
    ->middleware('active_permission:job-posting.update|job-posting.delete')
    ->name('alumni.job-postings.restore');
Route::delete('/alumni/job-postings/{id}/force', [JobPostingController::class, 'forceDestroy'])
    ->middleware('active_permission:job-posting.update|job-posting.delete')
    ->name('alumni.job-postings.force-destroy');
Route::post('/alumni/job-postings/{jobPosting}/toggle', [JobPostingController::class, 'toggle'])
    ->middleware('active_permission:job-posting.update')
    ->name('alumni.job-postings.toggle');
Route::get('/alumni/job-postings', [JobPostingController::class, 'index'])
    ->middleware('active_permission:job-posting.viewAny')
    ->name('alumni.job-postings.index');
Route::get('/alumni/job-postings/create', [JobPostingController::class, 'create'])
    ->middleware('active_permission:job-posting.create')
    ->name('alumni.job-postings.create');
Route::post('/alumni/job-postings', [JobPostingController::class, 'store'])
    ->middleware('active_permission:job-posting.create')
    ->name('alumni.job-postings.store');
Route::get('/alumni/job-postings/{jobPosting}/edit', [JobPostingController::class, 'edit'])
    ->middleware('active_permission:job-posting.update')
    ->name('alumni.job-postings.edit');
Route::put('/alumni/job-postings/{jobPosting}', [JobPostingController::class, 'update'])
    ->middleware('active_permission:job-posting.update')
    ->name('alumni.job-postings.update');
Route::delete('/alumni/job-postings/{jobPosting}', [JobPostingController::class, 'destroy'])
    ->middleware('active_permission:job-posting.delete')
    ->name('alumni.job-postings.destroy');
Route::get('/alumni/job-postings/{jobPosting}', [JobPostingController::class, 'show'])
    ->middleware('active_permission:job-posting.view')
    ->name('alumni.job-postings.show');

// CRUD Event / Agenda Alumni (Inertia React) - Batch AL-2.
Route::get('/alumni/alumni-events/export', [AlumniEventController::class, 'export'])
    ->middleware('active_permission:alumni-event.viewAny')
    ->name('alumni.alumni-events.export');
Route::post('/alumni/alumni-events/bulk-destroy', [AlumniEventController::class, 'bulkDestroy'])
    ->middleware('active_permission:alumni-event.delete')
    ->name('alumni.alumni-events.bulk-destroy');
Route::post('/alumni/alumni-events/bulk-restore', [AlumniEventController::class, 'bulkRestore'])
    ->middleware('active_permission:alumni-event.update|alumni-event.delete')
    ->name('alumni.alumni-events.bulk-restore');
Route::post('/alumni/alumni-events/bulk-force-destroy', [AlumniEventController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:alumni-event.update|alumni-event.delete')
    ->name('alumni.alumni-events.bulk-force-destroy');
Route::post('/alumni/alumni-events/{id}/restore', [AlumniEventController::class, 'restore'])
    ->middleware('active_permission:alumni-event.update|alumni-event.delete')
    ->name('alumni.alumni-events.restore');
Route::delete('/alumni/alumni-events/{id}/force', [AlumniEventController::class, 'forceDestroy'])
    ->middleware('active_permission:alumni-event.update|alumni-event.delete')
    ->name('alumni.alumni-events.force-destroy');
Route::post('/alumni/alumni-events/{alumniEvent}/toggle', [AlumniEventController::class, 'toggle'])
    ->middleware('active_permission:alumni-event.update')
    ->name('alumni.alumni-events.toggle');
Route::get('/alumni/alumni-events', [AlumniEventController::class, 'index'])
    ->middleware('active_permission:alumni-event.viewAny')
    ->name('alumni.alumni-events.index');
Route::get('/alumni/alumni-events/create', [AlumniEventController::class, 'create'])
    ->middleware('active_permission:alumni-event.create')
    ->name('alumni.alumni-events.create');
Route::post('/alumni/alumni-events', [AlumniEventController::class, 'store'])
    ->middleware('active_permission:alumni-event.create')
    ->name('alumni.alumni-events.store');
Route::get('/alumni/alumni-events/{alumniEvent}/edit', [AlumniEventController::class, 'edit'])
    ->middleware('active_permission:alumni-event.update')
    ->name('alumni.alumni-events.edit');
Route::put('/alumni/alumni-events/{alumniEvent}', [AlumniEventController::class, 'update'])
    ->middleware('active_permission:alumni-event.update')
    ->name('alumni.alumni-events.update');
Route::delete('/alumni/alumni-events/{alumniEvent}', [AlumniEventController::class, 'destroy'])
    ->middleware('active_permission:alumni-event.delete')
    ->name('alumni.alumni-events.destroy');
Route::get('/alumni/alumni-events/{alumniEvent}', [AlumniEventController::class, 'show'])
    ->middleware('active_permission:alumni-event.view')
    ->name('alumni.alumni-events.show');

// CRUD Kampanye Tracer Study (Inertia React) - Batch AL-2.
Route::get('/alumni/tracer-study-campaigns/export', [TracerStudyCampaignController::class, 'export'])
    ->middleware('active_permission:tracer-study-campaign.viewAny')
    ->name('alumni.tracer-study-campaigns.export');
Route::post('/alumni/tracer-study-campaigns/bulk-destroy', [TracerStudyCampaignController::class, 'bulkDestroy'])
    ->middleware('active_permission:tracer-study-campaign.delete')
    ->name('alumni.tracer-study-campaigns.bulk-destroy');
Route::post('/alumni/tracer-study-campaigns/bulk-restore', [TracerStudyCampaignController::class, 'bulkRestore'])
    ->middleware('active_permission:tracer-study-campaign.update|tracer-study-campaign.delete')
    ->name('alumni.tracer-study-campaigns.bulk-restore');
Route::post('/alumni/tracer-study-campaigns/bulk-force-destroy', [TracerStudyCampaignController::class, 'bulkForceDestroy'])
    ->middleware('active_permission:tracer-study-campaign.update|tracer-study-campaign.delete')
    ->name('alumni.tracer-study-campaigns.bulk-force-destroy');
Route::post('/alumni/tracer-study-campaigns/{id}/restore', [TracerStudyCampaignController::class, 'restore'])
    ->middleware('active_permission:tracer-study-campaign.update|tracer-study-campaign.delete')
    ->name('alumni.tracer-study-campaigns.restore');
Route::delete('/alumni/tracer-study-campaigns/{id}/force', [TracerStudyCampaignController::class, 'forceDestroy'])
    ->middleware('active_permission:tracer-study-campaign.update|tracer-study-campaign.delete')
    ->name('alumni.tracer-study-campaigns.force-destroy');
Route::post('/alumni/tracer-study-campaigns/{tracerStudyCampaign}/activate', [TracerStudyCampaignController::class, 'activate'])
    ->middleware('active_permission:tracer-study-campaign.update')
    ->name('alumni.tracer-study-campaigns.activate');
Route::get('/alumni/tracer-study-campaigns/{tracerStudyCampaign}/analytics', [TracerStudyAnalyticsController::class, 'show'])
    ->middleware('active_permission:tracer-study-campaign.view')
    ->name('alumni.tracer-study.analytics');
Route::get('/alumni/tracer-study-campaigns/{id}/export/{format}', [TracerStudyAnalyticsController::class, 'export'])
    ->middleware('active_permission:tracer-study-campaign.view')
    ->whereIn('format', ['csv', 'xlsx', 'pdf'])
    ->name('alumni.tracer-study.export');
Route::get('/alumni/tracer-study-campaigns', [TracerStudyCampaignController::class, 'index'])
    ->middleware('active_permission:tracer-study-campaign.viewAny')
    ->name('alumni.tracer-study-campaigns.index');
Route::get('/alumni/tracer-study-campaigns/create', [TracerStudyCampaignController::class, 'create'])
    ->middleware('active_permission:tracer-study-campaign.create')
    ->name('alumni.tracer-study-campaigns.create');
Route::post('/alumni/tracer-study-campaigns', [TracerStudyCampaignController::class, 'store'])
    ->middleware('active_permission:tracer-study-campaign.create')
    ->name('alumni.tracer-study-campaigns.store');
Route::get('/alumni/tracer-study-campaigns/{tracerStudyCampaign}/edit', [TracerStudyCampaignController::class, 'edit'])
    ->middleware('active_permission:tracer-study-campaign.update')
    ->name('alumni.tracer-study-campaigns.edit');
Route::put('/alumni/tracer-study-campaigns/{tracerStudyCampaign}', [TracerStudyCampaignController::class, 'update'])
    ->middleware('active_permission:tracer-study-campaign.update')
    ->name('alumni.tracer-study-campaigns.update');
Route::delete('/alumni/tracer-study-campaigns/{tracerStudyCampaign}', [TracerStudyCampaignController::class, 'destroy'])
    ->middleware('active_permission:tracer-study-campaign.delete')
    ->name('alumni.tracer-study-campaigns.destroy');
Route::get('/alumni/tracer-study-campaigns/{tracerStudyCampaign}', [TracerStudyCampaignController::class, 'show'])
    ->middleware('active_permission:tracer-study-campaign.view')
    ->name('alumni.tracer-study-campaigns.show');

// Respon Tracer Study (Inertia React) - Batch AL-2.
Route::get('/alumni/tracer-study-responses/export', [TracerStudyResponseController::class, 'export'])
    ->middleware('active_permission:tracer-study-response.viewAny')
    ->name('alumni.tracer-study-responses.export');
Route::get('/alumni/tracer-study-responses', [TracerStudyResponseController::class, 'index'])
    ->middleware('active_permission:tracer-study-response.viewAny')
    ->name('alumni.tracer-study-responses.index');
Route::get('/alumni/tracer-study-responses/{tracerStudyResponse}', [TracerStudyResponseController::class, 'show'])
    ->middleware('active_permission:tracer-study-response.view')
    ->name('alumni.tracer-study-responses.show');
