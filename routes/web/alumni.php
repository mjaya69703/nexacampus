<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/dashboard', 'alumni.dashboard.index')->name('dashboard.index');
Route::livewire('/profile', 'alumni.profile.index')->name('profile.index');
Route::livewire('/profile/edit', 'alumni.profile.edit')->name('profile.edit');
Route::livewire('/jobs', 'alumni.jobs.index')->name('jobs.index');
Route::livewire('/jobs/{id}', 'alumni.jobs.show')->name('jobs.show');
Route::livewire('/events', 'alumni.events.index')->name('events.index');
Route::livewire('/events/{id}', 'alumni.events.show')->name('events.show');
Route::livewire('/events/{id}/register', 'alumni.events.register')->name('events.register');
Route::livewire('/tracer-study', 'alumni.tracer-study.index')->name('tracer-study.index');
Route::livewire('/tracer-study/{id}', 'alumni.tracer-study.show')->name('tracer-study.show');
Route::livewire('/tracer-study/{id}/fill', 'alumni.tracer-study.fill')->name('tracer-study.fill');
