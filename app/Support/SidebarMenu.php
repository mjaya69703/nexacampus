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
            ->concat(static::employeeMenus($role))
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

        if (Route::currentRouteName() && str_starts_with(Route::currentRouteName(), $menu->route_name.'.')) {
            return true;
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

    protected static function employeeMenus(?string $role = null): Collection
    {
        $employeeProfile = auth()->user()?->employeeProfile;

        if (! $employeeProfile || ! $employeeProfile->is_active) {
            return collect();
        }

        $children = [
            static::makeChildLink('employee.attendance.index', 'Absensi Saya'),
            static::makeChildLink('employee.leaves.index', 'Cuti Saya'),
        ];

        if ($role !== 'lecturer') {
            $children[] = static::makeChildLink('employee.tridharma.index', 'Tridharma Saya');
        }

        return collect([
            static::makeGroup('employee-self-service', 'Kepegawaian Saya', 'fas fa-id-badge', $children),
        ]);
    }

    protected static function roleMenus(?string $role): Collection
    {
        return match ($role) {
            'student' => collect([
                static::makeGroup('student-academic', 'Akademik', 'fas fa-graduation-cap', [
                    static::makeChildLink('student.registration.index', 'Registrasi'),
                    static::makeChildLink('student.study-plan.index', 'KRS'),
                    static::makeChildLink('student.study-plan.comparison', 'Banding KRS'),
                    static::makeChildLink('student.schedule.index', 'Jadwal'),
                    static::makeChildLink('student.progress.index', 'Progress'),
                ]),
                static::makeGroup('student-learning', 'Pembelajaran', 'fas fa-book-open', [
                    static::makeChildLink('student.course-materials.index', 'Materi'),
                    static::makeChildLink('student.assignments.index', 'Tugas'),
                    static::makeChildLink('student.grades.index', 'Nilai'),
                    static::makeChildLink('student.transcript.index', 'Transkrip'),
                    static::makeChildLink('student.edom.index', 'Evaluasi Dosen'),
                ]),
                static::makeGroup('student-financial', 'Keuangan', 'fas fa-wallet', [
                    static::makeChildLink('student.financial.invoices', 'Tagihan'),
                ]),
                static::makeGroup('student-services', 'Layanan', 'fas fa-hands-helping', [
                    static::makeChildLink('student.digital-id.index', 'Kartu Mahasiswa'),
                    static::makeChildLink('student.student-services.letters', 'Surat'),
                    static::makeChildLink('student.student-services.leaves', 'Cuti Akademik'),
                    static::makeChildLink('student.student-services.transfers', 'Pindah Program'),
                    static::makeChildLink('student.student-services.graduations', 'Yudisium'),
                    static::makeChildLink('student.student-services.complaints', 'Pengaduan'),
                ]),
                static::makeGroup('student-publication', 'Publikasi', 'fas fa-bullhorn', [
                    static::makeChildLink('student.announcements.index', 'Pengumuman'),
                ]),
            ]),
            'alumni' => collect([
                static::makeGroup('alumni-career', 'Karir & Lowongan', 'fas fa-briefcase', [
                    static::makeChildLink('alumni.jobs.index', 'Job Board'),
                ]),
                static::makeGroup('alumni-events', 'Event & Networking', 'fas fa-calendar-star', [
                    static::makeChildLink('alumni.events.index', 'Event Alumni'),
                ]),
                static::makeGroup('alumni-tracer', 'Tracer Study', 'fas fa-chart-bar', [
                    static::makeChildLink('alumni.tracer-study.index', 'Survei Tracer'),
                ]),
            ]),
            'lecturer' => static::lecturerMenus(),
            'academic-leader' => collect([
                static::makeGroup('academic-leader-oversight', 'Operasional Akademik', 'fas fa-chart-line', [
                    static::makeChildLink('academic-leader.lecturers.index', 'Dosen'),
                    static::makeChildLink('academic-leader.classes.index', 'Kelas & Kehadiran'),
                ]),
                static::makeGroup('academic-leader-performance', 'Evaluasi & Kinerja', 'fas fa-chart-bar', [
                    static::makeChildLink('academic-leader.workloads.index', 'BKD Dosen'),
                    static::makeChildLink('academic-leader.edom.index', 'EDOM & Performa'),
                ]),
                static::makeLink('academic-leader-reports', 'Laporan', 'academic-leader.reports.index', 'fas fa-file-export'),
            ]),
            default => collect(),
        };
    }

    protected static function lecturerMenus(): Collection
    {
        $menus = collect([
            static::makeGroup('lecturer-teaching', 'Mengajar', 'fas fa-chalkboard-teacher', [
                static::makeChildLink('lecturer.course-offerings.index', 'Kelas Saya'),
                static::makeChildLink('lecturer.calendar.index', 'Kalender Mengajar'),
            ]),
            static::makeGroup('lecturer-learning', 'Pembelajaran', 'fas fa-book-open', [
                static::makeChildLink('lecturer.course-materials.list', 'Materi'),
                static::makeChildLink('lecturer.assignments.index', 'Tugas'),
            ]),
            static::makeGroup('lecturer-assessment', 'Evaluasi', 'fas fa-chart-bar', [
                static::makeChildLink('lecturer.student-grades.index', 'Nilai'),
                static::makeChildLink('lecturer.student-grades.grade-book', 'Grade Book'),
                static::makeChildLink('lecturer.workloads.index', 'BKD Saya'),
            ]),
            static::makeGroup('lecturer-publication', 'Publikasi', 'fas fa-bullhorn', [
                static::makeChildLink('lecturer.announcements.index', 'Pengumuman'),
            ]),
            static::makeGroup('lecturer-tridharma', 'Tridharma', 'fas fa-flask', [
                static::makeChildLink('lecturer.tridharma.index', 'Tridharma Saya'),
            ]),
        ]);

        if (! static::lecturerHasAdvisorAssignments()) {
            return $menus;
        }

        $menus->splice(1, 0, [
            static::makeGroup('lecturer-advising', 'Bimbingan Akademik', 'fas fa-user-graduate', [
                static::makeChildLink('lecturer.academic-advising.index', 'Mahasiswa Bimbingan'),
            ]),
        ]);

        return $menus;
    }

    protected static function lecturerHasAdvisorAssignments(): bool
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;

        return app(AcademicAdvisorService::class)->hasActiveAssignmentsForLecturer($lecturerProfileId);
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
            'academic-leader' => 'academic-leader.dashboard.index',
            default => $role.'.dashboard.index',
        };
    }
}
