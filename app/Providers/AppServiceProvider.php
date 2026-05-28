<?php

namespace App\Providers;

use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Support\ActivePermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share campus and system settings with all views
        View::composer('*', function ($view) {
            $campus = Cache::rememberForever('global_campus', function () {
                return Campus::first();
            });
            $system = Cache::rememberForever('global_system', function () {
                return System::first();
            });
            $campus ??= (object) [
                'name' => config('app.name', 'NexaCampus'),
                'logo_horizontal' => asset('storage/images/default/logo-horizontal.png'),
                'alamat' => '-',
                'whatsapp' => '-',
                'email_info' => '-',
                'email_humas' => '-',
            ];
            $system ??= (object) [
                'app_name' => config('app.name', 'NexaCampus'),
                'app_description' => 'Sistem informasi akademik perguruan tinggi terpadu.',
                'app_url' => url('/'),
            ];
            $view->with([
                'campus' => $campus,
                'system' => $system,
                'user' => Auth::user(),
                'activeRole' => session('active_role') ?? null,
            ]);
        });

        // Blade directives for active permissions
        Blade::if('activecan', function (string $permission) {
            return ActivePermission::check($permission);
        });

        Blade::if('activecanany', function (array $permissions) {
            return ActivePermission::any($permissions);
        });

        Blade::if('activecanall', function (array $permissions) {
            return ActivePermission::all($permissions);
        });

        // Route macro for CRUD Livewire components
        Route::macro('crudLivewire', function (
            string $resource,
            string $componentPrefix,
            array $only = ['index', 'create', 'edit', 'delete'],
            string $uriPrefix = '/manage'
        ) {
            $singular = Str::singular($resource);
            $baseName = Str::after($componentPrefix, 'admin.');

            if (in_array('index', $only)) {
                Route::livewire("{$uriPrefix}/{$resource}", "{$componentPrefix}.index")
                    ->middleware("active_permission:{$singular}.viewAny")
                    ->name("{$baseName}.index");
            }

            if (in_array('create', $only)) {
                Route::livewire("{$uriPrefix}/{$resource}/create", "{$componentPrefix}.create")
                    ->middleware("active_permission:{$singular}.create")
                    ->name("{$baseName}.create");
            }

            if (in_array('edit', $only)) {
                Route::livewire("{$uriPrefix}/{$resource}/{id}/edit", "{$componentPrefix}.edit")
                    ->middleware("active_permission:{$singular}.update")
                    ->name("{$baseName}.edit");
            }

            if (in_array('show', $only)) {
                Route::livewire("{$uriPrefix}/{$resource}/{id}", "{$componentPrefix}.show")
                    ->middleware("active_permission:{$singular}.view")
                    ->name("{$baseName}.show");
            }

            if (in_array('delete', $only)) {
                Route::livewire("{$uriPrefix}/{$resource}/{id}/delete", "{$componentPrefix}.delete")
                    ->middleware("active_permission:{$singular}.delete")
                    ->name("{$baseName}.delete");
            }
        });
    }
}
