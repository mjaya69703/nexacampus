<?php

use App\Http\Controllers\Alumni\DashboardController;
use App\Http\Controllers\Alumni\JobBoardController;
use App\Http\Controllers\Alumni\PortalEventController;
use App\Http\Controllers\Alumni\PortalTracerStudyController;
use App\Http\Controllers\Alumni\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

// Profil alumni
Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

// Job board
Route::get('/jobs', [JobBoardController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job}', [JobBoardController::class, 'show'])->name('jobs.show');

// Event alumni — rute "/register" didahulukan sebelum show.
Route::get('/events', [PortalEventController::class, 'index'])->name('events.index');
Route::get('/events/{alumniEvent}/register', [PortalEventController::class, 'registerForm'])->name('events.register');
Route::post('/events/{alumniEvent}/register', [PortalEventController::class, 'register'])->name('events.register.store');
Route::get('/events/{alumniEvent}', [PortalEventController::class, 'show'])->name('events.show');

// Tracer study
Route::get('/tracer-study', [PortalTracerStudyController::class, 'index'])->name('tracer-study.index');
Route::get('/tracer-study/{tracerStudyCampaign}', [PortalTracerStudyController::class, 'show'])->name('tracer-study.show');
Route::get('/tracer-study/{tracerStudyCampaign}/fill', [PortalTracerStudyController::class, 'fill'])->name('tracer-study.fill');
Route::post('/tracer-study/{tracerStudyCampaign}/fill', [PortalTracerStudyController::class, 'submit'])->name('tracer-study.submit');
