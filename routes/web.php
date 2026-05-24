<?php

use App\Http\Controllers\Academic\AssignmentFileController;
use App\Http\Controllers\Academic\AssignmentReportExportController;
use App\Http\Controllers\Admin\Admission\AcceptanceLetterController;
use App\Http\Controllers\Admin\Admission\AdmissionDocumentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Financial\FinancialReportExportController;
use App\Http\Controllers\Financial\PaymentReceiptController;
use App\Http\Controllers\Lecturer\CourseMaterialController;
use App\Http\Controllers\Lecturer\GradeBookExportController;
use App\Http\Controllers\StudentService\ComplaintAttachmentController;
use App\Http\Controllers\StudentService\GraduationDocumentController;
use App\Http\Controllers\StudentService\ServiceLetterDownloadController;
use App\Support\ResourceRegistry;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::livewire('/welcome', 'setup-wizard')->name('system.setup-wizard');

Route::middleware('is_installed')->group(function () {
    Route::redirect('/admission', '/admission/apply')->name('admission.index');
    Route::livewire('/admission/apply', 'admission.apply')->name('admission.apply');
    Route::livewire('/admission/status', 'admission.status')->name('admission.status');
    Route::livewire('/admission/applications/{applicationNumber}/{token}', 'admission.portal')->name('admission.portal');
    Route::get('/admission/applications/{applicationNumber}/{token}/documents/{document}/preview', [AdmissionDocumentController::class, 'portalPreview'])
        ->name('admission.documents.preview');

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

            Route::livewire('/financial/dashboard', 'admin.financial.dashboard.index')
                ->middleware('active_permission:financial-dashboard.viewAny')
                ->name('financial.dashboard.index');
            Route::livewire('/student-services/dashboard', 'admin.student-services.dashboard.index')
                ->middleware('active_permission:student-service-dashboard.viewAny')
                ->name('student-services.dashboard.index');

            Route::livewire('/academic/course-offerings/{offeringId}/attendance-sessions/{id}', 'admin.academic.attendance-sessions.show')
                ->middleware('active_permission:course-offering.view')
                ->name('academic.attendance-sessions.show');

            Route::get('/admission/documents/{document}/preview', [AdmissionDocumentController::class, 'adminPreview'])
                ->middleware('active_permission:admission-application.view')
                ->name('admission.documents.preview');

            Route::get('/admission/applications/{application}/acceptance-letter', [AcceptanceLetterController::class, 'show'])
                ->middleware('active_permission:admission-application.view')
                ->name('admission.applications.acceptance-letter');

            Route::livewire('/dashboard', 'admin.dashboard.index')->name('dashboard.index');

            Route::get('/financial/payments/{payment}/receipt', [PaymentReceiptController::class, 'admin'])
                ->middleware('active_permission:payment.view')
                ->name('financial.payments.receipt');

            Route::get('/financial/reports/export/csv', [FinancialReportExportController::class, 'csv'])
                ->middleware('active_permission:financial-report.viewAny')
                ->name('financial.reports.export.csv');
            Route::get('/financial/reports/export/xlsx', [FinancialReportExportController::class, 'xlsx'])
                ->middleware('active_permission:financial-report.viewAny')
                ->name('financial.reports.export.xlsx');
            Route::get('/financial/reports/export/pdf', [FinancialReportExportController::class, 'pdf'])
                ->middleware('active_permission:financial-report.viewAny')
                ->name('financial.reports.export.pdf');

            Route::get('/student-services/letter-requests/{request}/download', [ServiceLetterDownloadController::class, 'admin'])
                ->middleware('active_permission:service-letter-request.view')
                ->name('student-services.letter-requests.download');
            Route::get('/student-services/graduation-documents/{document}/preview', [GraduationDocumentController::class, 'adminPreview'])
                ->middleware('active_permission:graduation-application.view')
                ->name('student-services.graduation-documents.preview');
            Route::get('/student-services/complaint-attachments/{attachment}/download', [ComplaintAttachmentController::class, 'admin'])
                ->middleware('active_permission:student-complaint.view')
                ->name('student-services.complaint-attachments.download');
        });

        // Student Routes
        Route::middleware(['active_role:student', 'financial_clearance'])->prefix('student')->as('student.')->group(function () {
            Route::livewire('/dashboard', 'student.dashboard.index')->name('dashboard.index');
            Route::livewire('/registration', 'student.registration.index')->name('registration.index');
            Route::livewire('/study-plan', 'student.study-plan.index')->name('study-plan.index');
            Route::livewire('/grades', 'student.grades.index')->name('grades.index');
            Route::livewire('/financial/invoices', 'student.financial.invoices')->name('financial.invoices');
            Route::livewire('/financial/invoices/{id}', 'student.financial.invoice-detail')->name('financial.invoices.show');
            Route::get('/financial/payments/{payment}/receipt', [PaymentReceiptController::class, 'student'])->name('financial.payments.receipt');
            Route::livewire('/transcript', 'student.transcript.index')->name('transcript.index');
            Route::livewire('/schedule', 'student.schedule.index')->name('schedule.index');
            Route::livewire('/schedule/{offeringId}/attendance', 'student.schedule.attendance.index')->name('schedule.attendance');
            Route::livewire('/schedule/attendance/{sessionId}/record', 'student.schedule.attendance.record.index')->name('schedule.attendance.record');
            Route::livewire('/materials', 'student.course-materials')->name('course-materials.index');
            Route::livewire('/course-offerings/{offeringId}/materials', 'student.course-materials')->name('course-materials.offering');
            Route::livewire('/learning/{material}', 'student.learning.show')->name('learning.show');
            Route::livewire('/assignments', 'student.assignments.index')->name('assignments.index');
            Route::livewire('/assignments/{id}', 'student.assignments.show')->name('assignments.show');
            Route::get('/assignments/instructions/{file}', [AssignmentFileController::class, 'studentInstruction'])->name('assignments.instructions.preview');
            Route::get('/assignments/submissions/files/{file}', [AssignmentFileController::class, 'studentSubmission'])->name('assignments.submissions.files.preview');
            Route::get('/learning/{material}/preview/{fileId?}', [CourseMaterialController::class, 'preview'])->name('learning.preview');
            Route::get('/learning/{material}/link/{fileId}', [CourseMaterialController::class, 'openLink'])->name('learning.link');
            Route::get('/course-materials/{id}/download/{fileId}', [CourseMaterialController::class, 'download'])->name('course-materials.download');
            Route::livewire('/announcements', 'student.publication.announcements.index')->name('announcements.index');
            Route::livewire('/announcements/{id}', 'student.publication.announcements.show')->name('announcements.show');
            Route::livewire('/services/letters', 'student.student-services.letters')->name('student-services.letters');
            Route::livewire('/services/letters/create', 'student.student-services.letter-create')->name('student-services.letters.create');
            Route::livewire('/services/letters/{id}/edit', 'student.student-services.letter-edit')->name('student-services.letters.edit');
            Route::livewire('/services/letters/{id}', 'student.student-services.letter-detail')->name('student-services.letters.show');
            Route::get('/services/letters/{request}/download', [ServiceLetterDownloadController::class, 'student'])->name('student-services.letters.download');
            Route::livewire('/services/leaves', 'student.student-services.leaves')->name('student-services.leaves');
            Route::livewire('/services/leaves/create', 'student.student-services.leave-create')->name('student-services.leaves.create');
            Route::livewire('/services/leaves/{id}/edit', 'student.student-services.leave-edit')->name('student-services.leaves.edit');
            Route::livewire('/services/leaves/{id}', 'student.student-services.leave-detail')->name('student-services.leaves.show');
            Route::livewire('/services/transfers', 'student.student-services.transfers')->name('student-services.transfers');
            Route::livewire('/services/transfers/create', 'student.student-services.transfer-create')->name('student-services.transfers.create');
            Route::livewire('/services/transfers/{id}/edit', 'student.student-services.transfer-edit')->name('student-services.transfers.edit');
            Route::livewire('/services/transfers/{id}', 'student.student-services.transfer-detail')->name('student-services.transfers.show');
            Route::livewire('/services/graduations', 'student.student-services.graduations')->name('student-services.graduations');
            Route::livewire('/services/graduations/create', 'student.student-services.graduation-create')->name('student-services.graduations.create');
            Route::livewire('/services/graduations/{id}/edit', 'student.student-services.graduation-edit')->name('student-services.graduations.edit');
            Route::livewire('/services/graduations/{id}', 'student.student-services.graduation-detail')->name('student-services.graduations.show');
            Route::get('/services/graduation-documents/{document}/preview', [GraduationDocumentController::class, 'studentPreview'])->name('student-services.graduation-documents.preview');
            Route::livewire('/services/complaints', 'student.student-services.complaints')->name('student-services.complaints');
            Route::livewire('/services/complaints/create', 'student.student-services.complaint-create')->name('student-services.complaints.create');
            Route::livewire('/services/complaints/{id}', 'student.student-services.complaint-detail')->name('student-services.complaints.show');
            Route::get('/services/complaint-attachments/{attachment}/download', [ComplaintAttachmentController::class, 'student'])->name('student-services.complaint-attachments.download');
        });

        // Lecturer Routes
        Route::middleware('active_role:lecturer')->prefix('lecturer')->as('lecturer.')->group(function () {
            Route::livewire('/announcements', 'lecturer.publication.announcements.index')->name('announcements.index');
            Route::livewire('/announcements/create', 'lecturer.publication.announcements.create')->name('announcements.create');
            Route::livewire('/announcements/{id}/edit', 'lecturer.publication.announcements.edit')->name('announcements.edit');
            Route::livewire('/announcements/{id}', 'lecturer.publication.announcements.show')->name('announcements.show');
            Route::livewire('/dashboard', 'lecturer.dashboard.index')->name('dashboard.index');
            Route::livewire('/course-offerings', 'lecturer.course-offerings.index')->name('course-offerings.index');
            Route::livewire('/course-offerings/{id}', 'lecturer.course-offerings.show')->name('course-offerings.show');
            Route::livewire('/course-offerings/{offeringId}/students', 'lecturer.course-offerings.students')->name('course-offerings.students');
            Route::livewire('/course-offerings/{offeringId}/attendance', 'lecturer.course-offerings.attendance')->name('course-offerings.attendance');
            Route::livewire('/course-offerings/{offeringId}/grades', 'lecturer.course-offerings.grades')->name('course-offerings.grades');
            Route::livewire('/course-offerings/{offeringId}/materials', 'lecturer.course-materials.index')->name('course-materials.index');
            Route::livewire('/course-materials', 'lecturer.course-materials.list')->name('course-materials.list');
            Route::livewire('/course-materials/{material}', 'lecturer.course-materials.show')->name('course-materials.show');
            Route::get('/course-materials/{id}/download', [CourseMaterialController::class, 'download'])->name('course-materials.download');
            Route::get('/course-materials/{id}/preview/{fileId?}', [CourseMaterialController::class, 'preview'])->name('course-materials.preview');
            Route::get('/course-materials/{id}/link/{fileId}', [CourseMaterialController::class, 'openLink'])->name('course-materials.link');
            Route::livewire('/assignments', 'lecturer.assignments.index')->name('assignments.index');
            Route::livewire('/course-offerings/{offeringId}/assignments/create', 'lecturer.assignments.create')->name('assignments.create');
            Route::livewire('/assignments/{id}/edit', 'lecturer.assignments.edit')->name('assignments.edit');
            Route::livewire('/assignments/{id}', 'lecturer.assignments.show')->name('assignments.show');
            Route::get('/assignments/instructions/{file}', [AssignmentFileController::class, 'lecturerInstruction'])->name('assignments.instructions.preview');
            Route::get('/assignments/submissions/files/{file}', [AssignmentFileController::class, 'lecturerSubmission'])->name('assignments.submissions.files.preview');
            Route::get('/assignments/{assignment}/report/csv', [AssignmentReportExportController::class, 'csv'])->name('assignments.report.csv');
            Route::get('/assignments/{assignment}/report/xlsx', [AssignmentReportExportController::class, 'xlsx'])->name('assignments.report.xlsx');
            Route::get('/assignments/{assignment}/report/pdf', [AssignmentReportExportController::class, 'pdf'])->name('assignments.report.pdf');
            Route::livewire('/attendance-sessions/{sessionId}/edit', 'lecturer.attendance-sessions.edit')->name('attendance-sessions.edit');
            Route::livewire('/student-grades', 'lecturer.student-grades.index')->name('student-grades.index');
            Route::livewire('/student-grades/grade-book', 'lecturer.student-grades.grade-book')->name('student-grades.grade-book');
            Route::get('/student-grades/grade-book/export/csv', [GradeBookExportController::class, 'csv'])->name('student-grades.grade-book.export.csv');
            Route::get('/student-grades/grade-book/export/xlsx', [GradeBookExportController::class, 'xlsx'])->name('student-grades.grade-book.export.xlsx');
            Route::get('/student-grades/grade-book/export/pdf', [GradeBookExportController::class, 'pdf'])->name('student-grades.grade-book.export.pdf');
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
