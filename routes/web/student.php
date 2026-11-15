<?php

use App\Http\Controllers\Academic\AssignmentFileController;
use App\Http\Controllers\Academic\GradeAppealAttachmentController;
use App\Http\Controllers\Academic\ThesisDocumentController;
use App\Http\Controllers\Financial\PaymentReceiptController;
use App\Http\Controllers\Lecturer\CourseMaterialController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\StudentAffairsController;
use App\Http\Controllers\Student\StudentAnnouncementController;
use App\Http\Controllers\Student\StudentAssignmentController;
use App\Http\Controllers\Student\StudentConsultationController;
use App\Http\Controllers\Student\StudentDigitalIdController;
use App\Http\Controllers\Student\StudentEdomController;
use App\Http\Controllers\Student\StudentFinancialController;
use App\Http\Controllers\Student\StudentGradeAppealController;
use App\Http\Controllers\Student\StudentGradesController;
use App\Http\Controllers\Student\StudentLearningController;
use App\Http\Controllers\Student\StudentLibraryController;
use App\Http\Controllers\Student\StudentProgressController;
use App\Http\Controllers\Student\StudentRegistrationController;
use App\Http\Controllers\Student\StudentScheduleController;
use App\Http\Controllers\Student\StudentServiceController;
use App\Http\Controllers\Student\StudentStudyPlanController;
use App\Http\Controllers\Student\StudentThesisController;
use App\Http\Controllers\Student\StudentTranscriptController;
use App\Http\Controllers\StudentService\ComplaintAttachmentController;
use App\Http\Controllers\StudentService\GraduationDocumentController;
use App\Http\Controllers\StudentService\ServiceLetterDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/registration', [StudentRegistrationController::class, 'index'])->name('registration.index');
Route::post('/registration/draft', [StudentRegistrationController::class, 'saveDraft'])->name('registration.draft');
Route::post('/registration/submit', [StudentRegistrationController::class, 'submit'])->name('registration.submit');
Route::post('/registration/cancel', [StudentRegistrationController::class, 'cancel'])->name('registration.cancel');
Route::get('/digital-id', [StudentDigitalIdController::class, 'index'])->name('digital-id.index');
Route::get('/study-plan', [StudentStudyPlanController::class, 'index'])->name('study-plan.index');
Route::post('/study-plan/courses', [StudentStudyPlanController::class, 'store'])->name('study-plan.courses.store');
Route::delete('/study-plan/courses/{detail}', [StudentStudyPlanController::class, 'destroy'])->name('study-plan.courses.destroy');
Route::post('/study-plan/submit', [StudentStudyPlanController::class, 'submit'])->name('study-plan.submit');
Route::post('/study-plan/revise', [StudentStudyPlanController::class, 'revise'])->name('study-plan.revise');
Route::get('/study-plan/comparison', [StudentStudyPlanController::class, 'comparison'])->name('study-plan.comparison');
Route::get('/progress', [StudentProgressController::class, 'index'])->name('progress.index');
Route::get('/grades', [StudentGradesController::class, 'index'])->name('grades.index');
Route::get('/grade-appeals', [StudentGradeAppealController::class, 'index'])->name('grade-appeals.index');
Route::post('/grade-appeals', [StudentGradeAppealController::class, 'store'])->name('grade-appeals.store');
Route::get('/grade-appeal-attachments/{attachment}/preview', [GradeAppealAttachmentController::class, 'studentPreview'])->name('grade-appeal-attachments.preview');
Route::get('/financial/invoices', [StudentFinancialController::class, 'invoices'])->name('financial.invoices');
Route::get('/financial/invoices/{id}', [StudentFinancialController::class, 'show'])->whereNumber('id')->name('financial.invoices.show');
Route::post('/financial/invoices/{id}/payment', [StudentFinancialController::class, 'pay'])->whereNumber('id')->name('financial.invoices.pay');
Route::post('/financial/invoices/{id}/installment', [StudentFinancialController::class, 'requestInstallment'])->whereNumber('id')->name('financial.invoices.installment');
Route::get('/financial/payments/{payment}/receipt', [PaymentReceiptController::class, 'student'])->name('financial.payments.receipt');
Route::get('/transcript', [StudentTranscriptController::class, 'index'])->name('transcript.index');
Route::get('/schedule', [StudentScheduleController::class, 'index'])->name('schedule.index');
Route::get('/schedule/{offeringId}/attendance', [StudentScheduleController::class, 'attendance'])->whereNumber('offeringId')->name('schedule.attendance');
Route::get('/schedule/attendance/{sessionId}/record', [StudentScheduleController::class, 'record'])->whereNumber('sessionId')->name('schedule.attendance.record');
Route::post('/schedule/attendance/{sessionId}/record', [StudentScheduleController::class, 'scan'])->whereNumber('sessionId')->name('schedule.attendance.record.scan');
Route::get('/materials', [StudentLearningController::class, 'materials'])->name('course-materials.index');
Route::get('/course-offerings/{offering}/materials', [StudentLearningController::class, 'offeringMaterials'])->name('course-materials.offering');
Route::post('/materials/{material}/bookmark', [StudentLearningController::class, 'toggleBookmark'])->name('learning.bookmark');
Route::post('/materials/{material}/like', [StudentLearningController::class, 'toggleLike'])->name('learning.like');
Route::get('/learning/{material}', [StudentLearningController::class, 'show'])->name('learning.show');
Route::post('/learning/{material}/comments', [StudentLearningController::class, 'storeComment'])->name('learning.comments.store');
Route::patch('/learning/comments/{comment}', [StudentLearningController::class, 'updateComment'])->name('learning.comments.update');
Route::delete('/learning/comments/{comment}', [StudentLearningController::class, 'destroyComment'])->name('learning.comments.destroy');
Route::post('/learning/comments/{comment}/like', [StudentLearningController::class, 'toggleCommentLike'])->name('learning.comments.like');
Route::get('/assignments', [StudentAssignmentController::class, 'index'])->name('assignments.index');
Route::get('/assignments/{id}', [StudentAssignmentController::class, 'show'])->whereNumber('id')->name('assignments.show');
Route::post('/assignments/{id}/submission', [StudentAssignmentController::class, 'submit'])->whereNumber('id')->name('assignments.submission.store');
Route::delete('/assignments/submissions/files/{file}', [StudentAssignmentController::class, 'removeFile'])->name('assignments.submissions.files.destroy');
Route::get('/consultations', [StudentConsultationController::class, 'index'])->name('consultations.index');
Route::post('/consultations/{slot}/book', [StudentConsultationController::class, 'book'])->name('consultations.book');
Route::post('/consultations/{appointment}/cancel', [StudentConsultationController::class, 'cancel'])->name('consultations.cancel');
Route::get('/assignments/instructions/{file}', [AssignmentFileController::class, 'studentInstruction'])->name('assignments.instructions.preview');
Route::get('/assignments/submissions/files/{file}', [AssignmentFileController::class, 'studentSubmission'])->name('assignments.submissions.files.preview');
Route::get('/learning/{material}/preview/{fileId?}', [CourseMaterialController::class, 'preview'])->name('learning.preview');
Route::get('/learning/{material}/link/{fileId}', [CourseMaterialController::class, 'openLink'])->name('learning.link');
Route::get('/course-materials/{id}/download/{fileId?}', [CourseMaterialController::class, 'download'])->name('course-materials.download');
Route::get('/announcements', [StudentAnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{id}', [StudentAnnouncementController::class, 'show'])->whereNumber('id')->name('announcements.show');
Route::get('/announcements/{id}/attachment', [StudentAnnouncementController::class, 'downloadAttachment'])->whereNumber('id')->name('announcements.attachment');
Route::get('/edom', [StudentEdomController::class, 'index'])->name('edom.index');
Route::get('/edom/{periodId}/{courseOfferingId}/{lecturerProfileId}', [StudentEdomController::class, 'show'])
    ->whereNumber('periodId')->whereNumber('courseOfferingId')->whereNumber('lecturerProfileId')
    ->name('edom.show');
Route::post('/edom/{periodId}/{courseOfferingId}/{lecturerProfileId}', [StudentEdomController::class, 'submit'])
    ->whereNumber('periodId')->whereNumber('courseOfferingId')->whereNumber('lecturerProfileId')
    ->name('edom.submit');
$svc = StudentServiceController::class;
Route::get('/services/letters', [$svc, 'lettersIndex'])->name('student-services.letters');
Route::get('/services/letters/create', [$svc, 'lettersCreate'])->name('student-services.letters.create');
Route::post('/services/letters', [$svc, 'lettersStore'])->name('student-services.letters.store');
Route::get('/services/letters/{id}/edit', [$svc, 'lettersEdit'])->whereNumber('id')->name('student-services.letters.edit');
Route::match(['put', 'patch'], '/services/letters/{id}', [$svc, 'lettersUpdate'])->whereNumber('id')->name('student-services.letters.update');
Route::get('/services/letters/{id}', [$svc, 'lettersShow'])->whereNumber('id')->name('student-services.letters.show');
Route::get('/services/letters/{request}/download', [ServiceLetterDownloadController::class, 'student'])->name('student-services.letters.download');
Route::get('/services/leaves', [$svc, 'leavesIndex'])->name('student-services.leaves');
Route::get('/services/leaves/create', [$svc, 'leavesCreate'])->name('student-services.leaves.create');
Route::post('/services/leaves', [$svc, 'leavesStore'])->name('student-services.leaves.store');
Route::get('/services/leaves/{id}/edit', [$svc, 'leavesEdit'])->whereNumber('id')->name('student-services.leaves.edit');
Route::match(['put', 'patch'], '/services/leaves/{id}', [$svc, 'leavesUpdate'])->whereNumber('id')->name('student-services.leaves.update');
Route::get('/services/leaves/{id}', [$svc, 'leavesShow'])->whereNumber('id')->name('student-services.leaves.show');
Route::get('/services/transfers', [$svc, 'transfersIndex'])->name('student-services.transfers');
Route::get('/services/transfers/create', [$svc, 'transfersCreate'])->name('student-services.transfers.create');
Route::post('/services/transfers', [$svc, 'transfersStore'])->name('student-services.transfers.store');
Route::get('/services/transfers/{id}/edit', [$svc, 'transfersEdit'])->whereNumber('id')->name('student-services.transfers.edit');
Route::match(['put', 'patch'], '/services/transfers/{id}', [$svc, 'transfersUpdate'])->whereNumber('id')->name('student-services.transfers.update');
Route::get('/services/transfers/{id}', [$svc, 'transfersShow'])->whereNumber('id')->name('student-services.transfers.show');
Route::get('/services/graduations', [$svc, 'graduationsIndex'])->name('student-services.graduations');
Route::get('/services/graduations/create', [$svc, 'graduationsCreate'])->name('student-services.graduations.create');
Route::post('/services/graduations', [$svc, 'graduationsStore'])->name('student-services.graduations.store');
Route::get('/services/graduations/{id}/edit', [$svc, 'graduationsEdit'])->whereNumber('id')->name('student-services.graduations.edit');
Route::match(['put', 'patch'], '/services/graduations/{id}', [$svc, 'graduationsUpdate'])->whereNumber('id')->name('student-services.graduations.update');
Route::get('/services/graduations/{id}', [$svc, 'graduationsShow'])->whereNumber('id')->name('student-services.graduations.show');
Route::get('/services/graduation-documents/{document}/preview', [GraduationDocumentController::class, 'studentPreview'])->name('student-services.graduation-documents.preview');
Route::get('/services/complaints', [$svc, 'complaintsIndex'])->name('student-services.complaints');
Route::get('/services/complaints/create', [$svc, 'complaintsCreate'])->name('student-services.complaints.create');
Route::post('/services/complaints', [$svc, 'complaintsStore'])->name('student-services.complaints.store');
Route::get('/services/complaints/{id}', [$svc, 'complaintsShow'])->whereNumber('id')->name('student-services.complaints.show');
Route::post('/services/complaints/{id}/reply', [$svc, 'complaintsReply'])->whereNumber('id')->name('student-services.complaints.reply');
Route::post('/services/complaints/{id}/close', [$svc, 'complaintsClose'])->whereNumber('id')->name('student-services.complaints.close');
Route::get('/services/complaint-attachments/{attachment}/download', [ComplaintAttachmentController::class, 'student'])->name('student-services.complaint-attachments.download');

// Skripsi / Tugas Akhir (Inertia React).
Route::get('/thesis', [StudentThesisController::class, 'index'])->name('thesis.index');
Route::post('/thesis', [StudentThesisController::class, 'store'])->name('thesis.store');
Route::post('/thesis/cancel', [StudentThesisController::class, 'cancel'])->name('thesis.cancel');
Route::put('/thesis/{id}', [StudentThesisController::class, 'update'])->whereNumber('id')->name('thesis.update');
Route::post('/thesis/milestones/{milestone}/submit', [StudentThesisController::class, 'submitMilestone'])->name('thesis.milestones.submit');
Route::post('/thesis/documents', [StudentThesisController::class, 'storeDocument'])->name('thesis.documents.store');
Route::delete('/thesis/documents/{document}', [StudentThesisController::class, 'destroyDocument'])->name('thesis.documents.destroy');
Route::get('/thesis/documents/{document}/download', [ThesisDocumentController::class, 'student'])->name('thesis.documents.download');

// Perpustakaan (Inertia React).
Route::get('/library', [StudentLibraryController::class, 'index'])->name('library.index');
Route::get('/library/books/{book}', [StudentLibraryController::class, 'showBook'])->name('library.books.show');
Route::post('/library/reservations', [StudentLibraryController::class, 'storeReservation'])->name('library.reservations.store');
Route::post('/library/reservations/{reservation}/cancel', [StudentLibraryController::class, 'cancelReservation'])->name('library.reservations.cancel');
Route::post('/library/loans/{loan}/renew', [StudentLibraryController::class, 'renewLoan'])->name('library.loans.renew');
Route::post('/library/loans/{loan}/fine', [StudentLibraryController::class, 'submitFineProof'])->name('library.loans.fine.store');
Route::get('/library/loans/{loan}/fine/proof', [StudentLibraryController::class, 'downloadFineProof'])->name('library.loans.fine.proof');

// Kemahasiswaan — organisasi, prestasi, event, poin, dan booking fasilitas (Inertia React).
Route::get('/student-affairs', [StudentAffairsController::class, 'index'])->name('student-affairs.index');
Route::get('/student-affairs/achievements/{id}/certificate', [StudentAffairsController::class, 'downloadCertificate'])
    ->whereNumber('id')
    ->name('student-affairs.certificate');
Route::get('/student-affairs/events', [StudentAffairsController::class, 'events'])->name('student-affairs.events');
Route::post('/student-affairs/events/{id}/register', [StudentAffairsController::class, 'registerEvent'])
    ->whereNumber('id')
    ->name('student-affairs.events.register');
Route::post('/student-affairs/events/{id}/cancel-registration', [StudentAffairsController::class, 'cancelEventRegistration'])
    ->whereNumber('id')
    ->name('student-affairs.events.cancel-registration');
Route::get('/student-affairs/leaderboard', [StudentAffairsController::class, 'leaderboard'])->name('student-affairs.leaderboard');
Route::get('/student-affairs/bookings', [StudentAffairsController::class, 'bookings'])->name('student-affairs.bookings');
Route::post('/student-affairs/bookings', [StudentAffairsController::class, 'storeBooking'])->name('student-affairs.bookings.store');
Route::post('/student-affairs/bookings/{id}/cancel', [StudentAffairsController::class, 'cancelBooking'])
    ->whereNumber('id')
    ->name('student-affairs.bookings.cancel');
