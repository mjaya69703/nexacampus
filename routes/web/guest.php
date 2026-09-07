<?php

use App\Http\Controllers\Auth\AuthPageController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/login', [AuthPageController::class, 'login'])->name('auth.signin-index');
Route::post('/auth/login', [AuthPageController::class, 'authenticate'])->name('auth.login');
