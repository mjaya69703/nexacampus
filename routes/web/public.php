<?php

use App\Http\Controllers\Admin\Admission\AdmissionDocumentController;
use App\Http\Controllers\Home\AcademicPageController;
use App\Http\Controllers\Home\AdmissionPageController;
use App\Http\Controllers\Home\CommunityPageController;
use App\Http\Controllers\Home\InstitutionPageController;
use App\Http\Controllers\Home\PublicationPageController;
use App\Http\Controllers\Student\DigitalStudentIdVerificationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/admission', '/admission/apply')->name('root.admission.index');
Route::get('/admission/apply', [AdmissionPageController::class, 'apply'])->name('root.admission.apply');
Route::post('/admission/apply', [AdmissionPageController::class, 'store'])->name('root.admission.store');
Route::get('/admission/status', [AdmissionPageController::class, 'status'])->name('root.admission.status');
Route::post('/admission/status', [AdmissionPageController::class, 'checkStatus'])->name('root.admission.check');
Route::get('/admission/applications/{applicationNumber}/{token}', [AdmissionPageController::class, 'portal'])->name('root.admission.portal');
Route::post('/admission/applications/{applicationNumber}/{token}/documents', [AdmissionPageController::class, 'uploadDocument'])
    ->name('root.admission.documents.upload');
Route::get('/admission/applications/{applicationNumber}/{token}/documents/{document}/preview', [AdmissionDocumentController::class, 'portalPreview'])
    ->name('root.admission.documents.preview');
Route::get('/admission/requirements', [AdmissionPageController::class, 'requirements'])->name('root.admission.requirements');
Route::get('/admission/tuition', [AdmissionPageController::class, 'tuition'])->name('root.admission.tuition');
Route::get('/admission/faq', [AdmissionPageController::class, 'faq'])->name('root.admission.faq');
Route::get('/student-id/verify/{studentProfile}/{token}', DigitalStudentIdVerificationController::class)
    ->name('student.digital-id.verify');

Route::get('/program-studi', [AcademicPageController::class, 'programs'])->name('landing.prodi');
Route::get('/akademik/kalender', [AcademicPageController::class, 'calendar'])->name('root.akademik.kalender');
Route::get('/akademik/jadwal', [AcademicPageController::class, 'schedule'])->name('root.akademik.jadwal');
Route::get('/akademik/kurikulum', [AcademicPageController::class, 'curriculum'])->name('root.akademik.kurikulum');
Route::get('/akademik/elearning', [AcademicPageController::class, 'elearning'])->name('root.akademik.elearning');

Route::get('/beasiswa', [CommunityPageController::class, 'beasiswa'])->name('landing.beasiswa');
Route::get('/alumni', [CommunityPageController::class, 'alumniIndex'])->name('root.alumni.index');
Route::get('/alumni/karir', [CommunityPageController::class, 'alumniKarir'])->name('root.alumni.karir');
Route::get('/kemahasiswaan/organisasi', [CommunityPageController::class, 'organisasi'])->name('root.kemahasiswaan.organisasi');
Route::get('/kemahasiswaan/prestasi', [CommunityPageController::class, 'prestasi'])->name('root.kemahasiswaan.prestasi');
Route::get('/kemahasiswaan/layanan', [CommunityPageController::class, 'layanan'])->name('root.kemahasiswaan.layanan');

Route::get('/institusi/profil', [InstitutionPageController::class, 'profil'])->name('root.institusi.profil');
Route::get('/institusi/visi-misi', [InstitutionPageController::class, 'visiMisi'])->name('root.institusi.visi-misi');
Route::get('/institusi/struktur', [InstitutionPageController::class, 'struktur'])->name('root.institusi.struktur');
Route::get('/institusi/fasilitas', [InstitutionPageController::class, 'fasilitas'])->name('root.institusi.fasilitas');
Route::get('/institusi/akreditasi', [InstitutionPageController::class, 'akreditasi'])->name('root.institusi.akreditasi');
Route::get('/institusi/kerjasama', [InstitutionPageController::class, 'kerjasama'])->name('root.institusi.kerjasama');

Route::get('/pengumuman', [PublicationPageController::class, 'announcements'])->name('root.publication.announcements');
Route::get('/faq', [PublicationPageController::class, 'faq'])->name('root.faq');
Route::get('/berita', [PublicationPageController::class, 'news'])->name('root.publication.news');
Route::get('/berita/{slug}', [PublicationPageController::class, 'newsShow'])->name('root.publication.news-show');
Route::get('/agenda', [PublicationPageController::class, 'agenda'])->name('root.publication.agenda');
Route::get('/agenda/{slug}', [PublicationPageController::class, 'agendaShow'])->name('root.publication.agenda-show');
Route::get('/galeri', [PublicationPageController::class, 'galeri'])->name('root.publication.galeri');
Route::get('/kontak', [PublicationPageController::class, 'kontak'])->name('root.kontak');
