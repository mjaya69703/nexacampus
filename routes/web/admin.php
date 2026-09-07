<?php

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
    Route::crudLivewire(
        $resource['plural'],
        $resource['component'],
        $resource['actions'],
        $resource['area'],
        $resource['resource']
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

Route::livewire('/dashboard', 'admin.dashboard.index')->name('dashboard.index');

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
