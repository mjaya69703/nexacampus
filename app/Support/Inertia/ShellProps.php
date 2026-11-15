<?php

namespace App\Support\Inertia;

use App\Models\Academic\AcademicYear;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Models\User;
use App\Support\SidebarMenu;
use Illuminate\Support\Facades\Route;

/**
 * Builder props untuk AdminShell React — replika layouts.app
 * (sidebar + topbar + footer) yang dipakai semua halaman Inertia
 * di dalam portal (Shared/Profile, Shared/Employee, ...).
 */
final class ShellProps
{
    /**
     * @return array<string, mixed>
     */
    public static function make(User $user, string $pretitle, string $pageTitle): array
    {
        $activeRole = session('active_role') ?: $user->roles->first()?->name;
        $dashboardRoute = $user->prefix.'dashboard.index';

        $menus = SidebarMenu::get()->map(fn ($menu) => [
            'id' => $menu->id ?? $menu->title,
            'type' => $menu->type,
            'title' => $menu->title,
            'icon' => $menu->icon,
            'url' => static::menuUrl($menu),
            'isActive' => (bool) ($menu->is_active_menu ?? false),
            'children' => collect($menu->children ?? [])->map(fn ($child) => [
                'title' => $child->title,
                'url' => static::menuUrl($child),
                'isActive' => (bool) ($child->is_active_menu ?? false),
            ])->values()->all(),
        ])->values()->all();

        $commandItems = collect($menus)->flatMap(function (array $menu) {
            if ($menu['type'] === 'link') {
                return [[
                    'group' => null,
                    'title' => $menu['title'],
                    'href' => $menu['url'],
                    'icon' => $menu['icon'] ?: 'fas fa-arrow-right',
                ]];
            }

            return collect($menu['children'])->map(fn (array $child) => [
                'group' => $menu['title'],
                'title' => $child['title'],
                'href' => $child['url'],
                'icon' => $menu['icon'] ?: 'fas fa-arrow-right',
            ]);
        })->filter(fn (array $item) => filled($item['href']) && $item['href'] !== '#')->values()->all();

        return [
            'campusName' => Campus::value('name') ?? config('app.name', 'NexaCampus'),
            'campusLogo' => Campus::value('logo_horizontal') ?? asset('storage/images/default/logo-horizontal.png'),
            'appName' => System::value('app_name') ?? config('app.name', 'NexaCampus'),
            'appVersion' => System::value('app_version') ?? 'v1.0.0',
            'activePeriod' => AcademicYear::query()->where('is_active', true)->orderByDesc('start_date')->value('name'),
            'pretitle' => $pretitle,
            'pageTitle' => $pageTitle,
            'dashboardUrl' => $dashboardRoute && Route::has($dashboardRoute)
                ? route($dashboardRoute)
                : route('auth.select-role'),
            'profileUrl' => route('home.profile-index'),
            'switchRoleUrl' => route('auth.switch-role'),
            'logoutUrl' => route('auth.logout'),
            'homeUrl' => route('root.home-index'),
            'admissionStatusUrl' => route('root.admission.status'),
            'announcementsUrl' => route('root.publication.announcements'),
            'sourceUrl' => config('app.source_url', 'https://github.com/mjaya69703/nexacampus'),
            'user' => [
                'name' => $user->name,
                'photo' => $user->photo,
                'roleLabel' => $activeRole ? ucwords(str_replace('-', ' ', $activeRole)) : 'User',
            ],
            'menus' => $menus,
            'commandItems' => $commandItems,
        ];
    }

    private static function menuUrl(mixed $menu): string
    {
        $routeName = $menu->route_name ?? null;

        if ($routeName && Route::has($routeName)) {
            return route($routeName);
        }

        return $menu->url ?? '#';
    }
}
