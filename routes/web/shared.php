<?php

use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Notifications\PushSubscriptionController;
use App\Http\Controllers\Organization\TridharmaAttachmentController;
use App\Http\Controllers\Organization\UserDevelopmentAttachmentController;
use App\Http\Controllers\Shared\Employee\AttendanceController;
use App\Http\Controllers\Shared\Employee\LeaveController;
use App\Http\Controllers\Shared\Employee\TridharmaController;
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
Route::get('/employee/attendance', [AttendanceController::class, 'index'])->name('employee.attendance.index');
Route::post('/employee/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('employee.attendance.check-in');
Route::post('/employee/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('employee.attendance.check-out');
Route::get('/employee/leaves', fn () => redirect()->route('employee.attendance.index', ['tab' => 'cuti']))->name('employee.leaves.index');
Route::post('/employee/leaves', [LeaveController::class, 'store'])->name('employee.leaves.store');
Route::get('/employee/tridharma', [TridharmaController::class, 'index'])->name('employee.tridharma.index');
Route::get('/employee/tridharma/create', [TridharmaController::class, 'create'])->name('employee.tridharma.create');
Route::post('/employee/tridharma', [TridharmaController::class, 'store'])->name('employee.tridharma.store');
Route::get('/employee/tridharma/{record}', [TridharmaController::class, 'show'])->name('employee.tridharma.show');
Route::post('/employee/tridharma/{record}/submit', [TridharmaController::class, 'submitApproval'])->name('employee.tridharma.submit');
Route::post('/employee/tridharma/{record}/milestones', [TridharmaController::class, 'storeMilestone'])->name('employee.tridharma.milestones.store');
Route::patch('/employee/tridharma/{record}/milestones/{milestone}', [TridharmaController::class, 'updateMilestone'])->name('employee.tridharma.milestones.update');
Route::post('/employee/tridharma/{record}/outputs', [TridharmaController::class, 'storeOutput'])->name('employee.tridharma.outputs.store');
Route::post('/employee/tridharma/{record}/members', [TridharmaController::class, 'storeMember'])->name('employee.tridharma.members.store');
Route::delete('/employee/tridharma/{record}/members/{member}', [TridharmaController::class, 'destroyMember'])->name('employee.tridharma.members.destroy');
Route::post('/employee/tridharma/{record}/attachments', [TridharmaController::class, 'storeAttachment'])->name('employee.tridharma.attachments.store');
Route::get('/employee/users/search', [TridharmaController::class, 'searchUsers'])->name('employee.users.search');
Route::get('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
Route::get('/auth/switch-role', [AuthController::class, 'switchRole'])->name('auth.switch-role');
