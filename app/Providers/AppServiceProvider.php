<?php

namespace App\Providers;

use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Support\ActivePermission;
use App\Support\WhatsApp\WindowsAwareSidecarManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Kstmostofa\LaravelWhatsApp\Web\SidecarManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SidecarManager::class, function ($app) {
            return new WindowsAwareSidecarManager($app['config']->get('laravel-whatsapp.web'));
        });
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
    }
}
