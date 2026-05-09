<?php

namespace App\Support;

use App\Models\Settings\Menu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Fluent;

class SidebarMenu
{
    public static function get(): Collection
    {
        $role = session('active_role');

        $menus = Menu::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $databaseMenus = $menus->filter(function ($menu) {
            if ($menu->type === 'link') {
                $menu->is_active_menu = static::isMenuActive($menu);

                return static::canSee($menu->permission_name);
            }

            if ($menu->type === 'group') {
                $visibleChildren = $menu->children->filter(function ($child) {
                    $child->is_active_menu = static::isMenuActive($child);

                    return static::canSee($child->permission_name);
                })->values();

                $menu->setRelation('children', $visibleChildren);
                $menu->is_active_menu = $visibleChildren->contains(fn ($child) => $child->is_active_menu);

                return $visibleChildren->isNotEmpty();
            }

            return false;
        })->values();

        return static::commonMenus($role)
            ->concat($databaseMenus)
            ->concat(static::roleMenus($role))
            ->unique(function ($menu) {
                $childrenKey = collect($menu->children ?? [])->pluck('route_name')->implode('|');

                return implode(':', [
                    $menu->type ?? 'link',
                    $menu->route_name ?? '',
                    $menu->title ?? '',
                    $childrenKey,
                ]);
            })
            ->values();
    }

    protected static function canSee(?string $permission): bool
    {
        if (blank($permission)) {
            return true;
        }

        return ActivePermission::check($permission);
    }

    protected static function isMenuActive($menu): bool
    {
        if (blank($menu->route_name)) {
            return false;
        }

        $patterns = [$menu->route_name];

        if (str($menu->route_name)->endsWith('.index')) {
            $patterns[] = str($menu->route_name)->beforeLast('.index').'.*';
        }

        foreach ($patterns as $pattern) {
            if (Route::currentRouteNamed($pattern)) {
                return true;
            }
        }

        return false;
    }

    protected static function commonMenus(?string $role): Collection
    {
        if (blank($role)) {
            return collect();
        }

        return collect([
            static::makeLink(
                id: 'common-dashboard',
                title: 'Dashboard',
                routeName: static::dashboardRouteName($role),
                icon: 'fas fa-home',
            ),
            static::makeLink(
                id: 'common-profile',
                title: 'Profil',
                routeName: 'home.profile-index',
                icon: 'fas fa-user',
            ),
        ]);
    }

    protected static function roleMenus(?string $role): Collection
    {
        return match ($role) {
            'student' => collect([
                static::makeLink('student-registration', 'Registrasi', 'student.registration.index', 'fas fa-clipboard-list'),
                static::makeLink('student-study-plan', 'KRS', 'student.study-plan.index', 'fas fa-file-alt'),
                static::makeLink('student-schedule', 'Jadwal', 'student.schedule.index', 'fas fa-calendar'),
                static::makeGroup('student-publication', 'Publikasi', 'fas fa-bullhorn', [
                    static::makeChildLink('student.announcements.index', 'Pengumuman'),
                ]),
                static::makeLink('student-materials', 'Materi', 'student.course-materials.index', 'fas fa-book-open'),
                static::makeLink('student-grades', 'Nilai', 'student.grades.index', 'fas fa-chart-bar'),
                static::makeLink('student-transcript', 'Transkrip', 'student.transcript.index', 'fas fa-file-invoice'),
            ]),
            'lecturer' => collect([
                static::makeLink('lecturer-course-offerings', 'Kelas Saya', 'lecturer.course-offerings.index', 'fas fa-book'),
                static::makeGroup('lecturer-publication', 'Publikasi', 'fas fa-bullhorn', [
                    static::makeChildLink('lecturer.announcements.index', 'Pengumuman'),
                ]),
                static::makeLink('lecturer-course-materials', 'Materi', 'lecturer.course-materials.list', 'fas fa-book-open'),
                static::makeLink('lecturer-student-grades', 'Nilai', 'lecturer.student-grades.index', 'fas fa-chart-bar'),
            ]),
            default => collect(),
        };
    }

    protected static function makeLink(string $id, string $title, string $routeName, ?string $icon = null): Fluent
    {
        return new Fluent([
            'id' => $id,
            'type' => 'link',
            'title' => $title,
            'route_name' => $routeName,
            'url' => null,
            'icon' => $icon,
            'permission_name' => null,
            'is_active_menu' => static::isMenuActive((object) ['route_name' => $routeName]),
        ]);
    }

    protected static function makeGroup(string $id, string $title, ?string $icon = null, array $children = []): Fluent
    {
        $childCollection = collect($children);

        return new Fluent([
            'id' => $id,
            'type' => 'group',
            'title' => $title,
            'route_name' => null,
            'url' => null,
            'icon' => $icon,
            'permission_name' => null,
            'children' => $childCollection,
            'is_active_menu' => $childCollection->contains(fn ($child) => (bool) ($child->is_active_menu ?? false)),
        ]);
    }

    protected static function makeChildLink(string $routeName, string $title): Fluent
    {
        return new Fluent([
            'type' => 'link',
            'title' => $title,
            'route_name' => $routeName,
            'url' => null,
            'icon' => null,
            'permission_name' => null,
            'is_active_menu' => static::isMenuActive((object) ['route_name' => $routeName]),
        ]);
    }

    protected static function dashboardRouteName(string $role): string
    {
        return match ($role) {
            'superuser' => 'admin.dashboard.index',
            default => $role.'.dashboard.index',
        };
    }
}
