<?php

use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Notifications\PushSubscriptionController;
use App\Http\Controllers\Organization\TridharmaAttachmentController;
use App\Http\Controllers\Organization\UserDevelopmentAttachmentController;
use App\Http\Controllers\Shared\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/select-role', [AuthPageController::class, 'selectRole'])->name('auth.select-role');
Route::post('/auth/select-role', [AuthPageController::class, 'storeRole'])->name('auth.select-role.store');
Route::get('/profile', [ProfileController::class, 'index'])->name('home.profile-index');
Route::post('/profile', [ProfileController::class, 'update'])->name('home.profile.update');
Route::post('/profile/password', [ProfileController::class, 'password'])->name('home.profile.password');
Route::post('/profile/certificates', [ProfileController::class, 'certificate'])->name('home.profile.certificates.store');
Route::get('/profile/development-attachments/{attachment}/preview', [UserDevelopmentAttachmentController::class, 'profilePreview'])
    ->name('profile.development-attachments.preview');
Route::post('/notifications/push-subscriptions', [PushSubscriptionController::class, 'store'])
    ->name('notifications.push-subscriptions.store');
Route::delete('/notifications/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
    ->name('notifications.push-subscriptions.destroy');
Route::get('/tridharma/attachments/{attachment}/preview', [TridharmaAttachmentController::class, 'selfPreview'])
    ->name('tridharma.attachments.preview');
Route::livewire('/employee/attendance', 'employee.attendance.index')->name('employee.attendance.index');
Route::livewire('/employee/leaves', 'employee.leaves.index')->name('employee.leaves.index');
Route::livewire('/employee/tridharma', 'employee.tridharma.index')->name('employee.tridharma.index');
Route::livewire('/employee/tridharma/create', 'employee.tridharma.create')->name('employee.tridharma.create');
Route::livewire('/employee/tridharma/{id}', 'employee.tridharma.show')->name('employee.tridharma.show');
Route::get('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
Route::get('/auth/switch-role', [AuthController::class, 'switchRole'])->name('auth.switch-role');
