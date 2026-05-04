<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureActiveRoleHasPermission;
use App\Http\Middleware\EnsureRoleIsActive;
use App\Http\Middleware\EnsureSystemIsInstalled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => Authenticate::class,
            // 'maintenance' => \App\Http\Middleware\CheckForMaintenanceMode::class,
            'active_role' => EnsureRoleIsActive::class,
            'active_permission' => EnsureActiveRoleHasPermission::class,
            'is_installed' => EnsureSystemIsInstalled::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            // 'active_subrole' => \App\Http\Middleware\EnsureSubroleIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
