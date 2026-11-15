<?php

use App\Http\Controllers\Home\WelcomeController;
use App\Http\Controllers\Setup\SetupWizardController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('root.home-index');
Route::get('/welcome', [SetupWizardController::class, 'show'])->name('system.setup-wizard');
Route::post('/welcome', [SetupWizardController::class, 'store'])->name('system.setup-wizard.store');

Route::middleware('is_installed')->group(function () {
    require __DIR__.'/web/public.php';

    Route::middleware('guest')->group(function () {
        require __DIR__.'/web/guest.php';
    });

    Route::middleware('auth')->group(function () {
        require __DIR__.'/web/shared.php';

        Route::middleware('active_role')->prefix('admin')->as('admin.')->group(function () {
            require __DIR__.'/web/admin.php';
        });

        Route::middleware(['active_role:student', 'financial_clearance'])
            ->prefix('student')
            ->as('student.')
            ->group(function () {
                require __DIR__.'/web/student.php';
            });

        Route::middleware('active_role:lecturer')->prefix('lecturer')->as('lecturer.')->group(function () {
            require __DIR__.'/web/lecturer.php';
        });

        Route::middleware('active_role:academic-leader')->prefix('academic-leader')->as('academic-leader.')->group(function () {
            require __DIR__.'/web/academic-leader.php';
        });

        Route::middleware('active_role:alumni')->prefix('alumni')->as('alumni.')->group(function () {
            require __DIR__.'/web/alumni.php';
        });
    });
});
