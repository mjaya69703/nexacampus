<?php

namespace App\Http\Controllers\Home;

use App\Enums\AchievementLevel;
use App\Enums\AchievementStatus;
use App\Enums\EventRegistrationStatus;
use App\Enums\OrganizationType;
use App\Enums\StudentEventCategory;
use App\Enums\StudentEventStatus;
use App\Http\Controllers\Controller;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\JobPosting;
use App\Models\Financial\Scholarship;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Models\StudentAffairs\StudentAchievement;
use App\Models\StudentAffairs\StudentEvent;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\StudentAffairs\StudentOrganizationMember;
use App\Support\Inertia\PublicUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CommunityPageController extends Controller
{
    public function beasiswa(Request $request): Response
    {
        $scholarships = Scholarship::where('is_active', true)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'description' => Str::limit(strip_tags((string) $s->description), 160),
                'requirements' => $s->requirements,
                'discount' => $s->discount_type === 'percentage'
                    ? (float) $s->discount_percentage.'%'
                    : 'Rp '.number_format($s->fixed_amount, 0, ',', '.'),
                'duration' => $s->duration_semesters,
            ])->all();

        return $this->render($request, 'Home/Kemahasiswaan/Beasiswa', ['scholarships' => $scholarships]);
    }

    public function organisasi(Request $request): Response
    {
        $organizations = StudentOrganization::with([
            'faculty:id,name,short_name',
            'studyProgram:id,name',
            'advisor:id,first_name,last_name',
        ])
            ->withCount('activeMembers')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (StudentOrganization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'type' => $organization->type->value,
                'typeLabel' => $organization->type->label(),
                'typeShort' => $organization->type->shortLabel(),
                'typeTone' => $organization->type->tone(),
                'faculty' => $organization->faculty?->short_name ?? $organization->faculty?->name,
                'studyProgram' => $organization->studyProgram?->name,
                'advisor' => $organization->advisor?->name,
                'description' => Str::limit(strip_tags((string) $organization->description), 180),
                'foundedAt' => $organization->founded_at?->format('Y'),
                'activeMembers' => (int) $organization->active_members_count,
                'logoUrl' => $organization->logo_path ? Storage::disk('public')->url($organization->logo_path) : null,
                'instagramUrl' => $organization->instagram_url,
                'contactEmail' => $organization->contact_email,
            ]);

        $stats = [
            'organizations' => StudentOrganization::where('is_active', true)->count(),
            'members' => StudentOrganizationMember::where('is_active', true)->count(),
            'types' => StudentOrganization::where('is_active', true)->distinct()->count('type'),
            'achievements' => StudentAchievement::where('status', AchievementStatus::VERIFIED->value)->count(),
        ];

        return $this->render($request, 'Home/Kemahasiswaan/Organisasi', [
            'organizations' => $organizations->values(),
            'stats' => $stats,
            'types' => OrganizationType::options(),
        ]);
    }

    public function prestasi(Request $request): Response
    {
        $achievements = StudentAchievement::with([
            'user:id,first_name,last_name',
            'user.studentProfile:id,user_id,nim,study_program_id',
            'user.studentProfile.studyProgram:id,name',
            'organization:id,name',
        ])
            ->where('status', AchievementStatus::VERIFIED->value)
            ->orderByDesc('is_featured')
            ->orderByDesc('achieved_at')
            ->limit(60)
            ->get()
            ->map(fn (StudentAchievement $achievement) => [
                'id' => $achievement->id,
                'title' => $achievement->title,
                'student' => $achievement->user?->name ?? '-',
                'nim' => $achievement->user?->studentProfile?->nim,
                'program' => $achievement->user?->studentProfile?->studyProgram?->name,
                'initials' => collect(explode(' ', (string) $achievement->user?->name))
                    ->filter()
                    ->take(2)
                    ->map(fn (string $word) => strtoupper($word[0] ?? ''))
                    ->implode(''),
                'organization' => $achievement->organization?->name,
                'category' => $achievement->category->value,
                'categoryLabel' => $achievement->category->label(),
                'categoryTone' => $achievement->category->tone(),
                'level' => $achievement->level->value,
                'levelLabel' => $achievement->level->label(),
                'levelTone' => $achievement->level->tone(),
                'rankLabel' => $achievement->rank->label(),
                'rankTone' => $achievement->rank->tone(),
                'achievedAt' => $achievement->achieved_at?->format('d M Y'),
                'year' => $achievement->achieved_at?->format('Y'),
                'organizer' => $achievement->organizer,
                'description' => Str::limit(strip_tags((string) $achievement->description), 220),
                'isFeatured' => (bool) $achievement->is_featured,
            ]);

        $verified = StudentAchievement::where('status', AchievementStatus::VERIFIED->value);

        $stats = [
            'total' => (clone $verified)->count(),
            'featured' => (clone $verified)->where('is_featured', true)->count(),
            'national' => (clone $verified)->whereIn('level', [
                AchievementLevel::NATIONAL->value,
                AchievementLevel::INTERNATIONAL->value,
            ])->count(),
            'students' => (clone $verified)->distinct()->count('user_id'),
        ];

        return $this->render($request, 'Home/Kemahasiswaan/Prestasi', [
            'achievements' => $achievements->values(),
            'stats' => $stats,
            'levels' => AchievementLevel::options(),
        ]);
    }

    public function layanan(Request $request): Response
    {
        return $this->render($request, 'Home/Kemahasiswaan/Layanan');
    }

    public function agenda(Request $request): Response
    {
        $events = StudentEvent::with('organization:id,name')
            ->withCount([
                'registrations as registered_count' => fn ($sub) => $sub->where('status', '!=', EventRegistrationStatus::CANCELLED->value),
            ])
            ->whereIn('status', [
                StudentEventStatus::PUBLISHED->value,
                StudentEventStatus::ONGOING->value,
            ])
            ->where(function ($query) {
                $query->where('starts_at', '>=', now()->startOfDay())
                    ->orWhere('status', StudentEventStatus::ONGOING->value);
            })
            ->orderBy('starts_at')
            ->limit(60)
            ->get();

        $mapped = $events->map(fn (StudentEvent $event) => $this->mapPublicEvent($event));

        $stats = [
            'events' => $mapped->count(),
            'upcoming' => $events->filter(fn (StudentEvent $event) => $event->starts_at?->isFuture())->count(),
            'participants' => (int) $events->sum('registered_count'),
            'organizations' => $events
                ->map(fn (StudentEvent $event) => $event->organization?->name ?? $event->organizer)
                ->filter()
                ->unique()
                ->count(),
        ];

        return $this->render($request, 'Home/Kemahasiswaan/Agenda', [
            'events' => $mapped->values(),
            'stats' => $stats,
            'categories' => StudentEventCategory::options(),
        ]);
    }

    public function agendaDetail(Request $request, string $slug): Response
    {
        $event = StudentEvent::with('organization:id,name')
            ->withCount([
                'registrations as registered_count' => fn ($sub) => $sub->where('status', '!=', EventRegistrationStatus::CANCELLED->value),
            ])
            ->where('slug', $slug)
            ->whereIn('status', [
                StudentEventStatus::PUBLISHED->value,
                StudentEventStatus::ONGOING->value,
                StudentEventStatus::COMPLETED->value,
            ])
            ->firstOrFail();

        $related = StudentEvent::with('organization:id,name')
            ->withCount([
                'registrations as registered_count' => fn ($sub) => $sub->where('status', '!=', EventRegistrationStatus::CANCELLED->value),
            ])
            ->whereKeyNot($event->id)
            ->where('category', $event->category->value)
            ->visibleToStudents()
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->limit(3)
            ->get()
            ->map(fn (StudentEvent $relatedEvent) => $this->mapPublicEvent($relatedEvent))
            ->values();

        $user = $request->user();
        $activeRole = $request->session()->get('active_role');
        $isStudent = $activeRole ? $activeRole === 'student' : ($user?->hasRole('student') ?? false);
        $dashboardRoute = $user ? $user->prefix.'dashboard.index' : null;
        $dashboardUrl = $dashboardRoute && Route::has($dashboardRoute) ? route($dashboardRoute) : route('auth.select-role');
        $eventData = $this->mapPublicEvent($event, true);

        return $this->render($request, 'Home/Kemahasiswaan/AgendaDetail', [
            'event' => $eventData,
            'related' => $related,
            'ctaUrl' => $user ? ($isStudent ? route('student.student-affairs.events') : $dashboardUrl) : route('auth.signin-index'),
            'ctaLabel' => $user
                ? ($isStudent ? 'Buka Portal Event' : 'Buka Dashboard')
                : ($eventData['registrationOpen'] ? 'Masuk untuk Mendaftar' : 'Masuk ke Portal'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPublicEvent(StudentEvent $event, bool $detailed = false): array
    {
        $registered = (int) ($event->registered_count ?? 0);
        $seatsLeft = $event->quota !== null ? max(0, $event->quota - $registered) : null;
        $registrationOpen = $event->status === StudentEventStatus::PUBLISHED
            && (! $event->registration_deadline || $event->registration_deadline->isFuture())
            && ($event->quota === null || $registered < $event->quota);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'category' => $event->category->value,
            'categoryLabel' => $event->category->label(),
            'categoryTone' => $event->category->tone(),
            'description' => $detailed
                ? strip_tags((string) $event->description)
                : Str::limit(strip_tags((string) $event->description), 180),
            'organization' => $event->organization?->name,
            'organizer' => $event->organizer,
            'location' => $event->location,
            'startsAt' => $event->starts_at?->format('d M Y H:i'),
            'startDay' => $event->starts_at?->format('d'),
            'startMonth' => $event->starts_at?->format('M'),
            'startYear' => $event->starts_at?->format('Y'),
            'startTime' => $event->starts_at?->format('H:i'),
            'endsAt' => $event->ends_at?->format('d M Y H:i'),
            'endTime' => $event->ends_at?->format('H:i'),
            'registrationDeadline' => $event->registration_deadline?->format('d M Y H:i'),
            'quota' => $event->quota,
            'registeredCount' => $registered,
            'seatsLeft' => $seatsLeft,
            'points' => (int) $event->points,
            'status' => $event->status->value,
            'statusLabel' => $event->status->label(),
            'statusTone' => $event->status->tone(),
            'isFeatured' => (bool) $event->is_featured,
            'coverUrl' => $event->cover_path ? Storage::disk('public')->url($event->cover_path) : null,
            'registrationOpen' => $registrationOpen,
            'detailUrl' => route('root.kemahasiswaan.agenda.show', $event->slug),
        ];
    }

    public function alumniIndex(Request $request): Response
    {
        $stats = [
            'total' => AlumniProfile::where('is_active', true)->count(),
            'employed' => AlumniProfile::where('is_active', true)->where('employment_status', 'employed')->count(),
            'programs' => StudyProgram::where('is_active', true)->count(),
            'jobs' => JobPosting::where('is_active', true)->whereDate('deadline_date', '>=', now())->count(),
        ];

        $alumni = AlumniProfile::with(['studyProgram', 'faculty'])
            ->where('is_active', true)
            ->whereNotNull('employment_status')
            ->latest('graduation_date')
            ->limit(9)
            ->get()
            ->map(fn ($a) => [
                'name' => $a->full_name,
                'nim' => $a->nim,
                'program' => $a->studyProgram?->name ?? '-',
                'faculty' => $a->faculty?->short_name ?? '-',
                'graduationYear' => $a->graduation_year,
                'gpa' => $a->gpa,
                'employer' => $a->employer_name,
                'jobTitle' => $a->job_title,
                'city' => $a->current_city,
                'employmentStatus' => $a->employment_status,
                'linkedin' => $a->linkedin_url,
                'initials' => collect(explode(' ', (string) $a->full_name))->take(2)->map(fn ($w) => strtoupper($w[0] ?? ''))->implode(''),
            ])->all();

        $jobs = JobPosting::where('is_active', true)
            ->whereDate('deadline_date', '>=', now())
            ->orderBy('deadline_date')
            ->limit(6)
            ->get()
            ->map(fn ($j) => [
                'id' => $j->id,
                'title' => $j->title,
                'company' => $j->company_name,
                'location' => $j->location,
                'jobType' => $j->job_type,
                'industry' => $j->industry,
                'salary' => $j->salary_range,
                'deadline' => $j->deadline_date?->format('d M Y'),
                'applyUrl' => $j->apply_url,
            ])->all();

        return $this->render($request, 'Home/Alumni/Index', [
            'stats' => $stats,
            'alumni' => $alumni,
            'jobs' => $jobs,
        ]);
    }

    public function alumniKarir(Request $request): Response
    {
        $query = JobPosting::with('employerPartner')
            ->where('is_active', true)
            ->whereDate('deadline_date', '>=', now())
            ->orderBy('deadline_date');

        $totalJobs = $query->count();

        $jobs = $query->get()->map(fn ($j) => [
            'id' => $j->id,
            'title' => $j->title,
            'company' => $j->company_name,
            'industry' => $j->industry,
            'location' => $j->location,
            'jobType' => $j->job_type,
            'salary' => $j->salary_range,
            'description' => Str::limit(strip_tags((string) $j->description), 150),
            'requirements' => Str::limit(strip_tags((string) $j->requirements), 150),
            'deadline' => $j->deadline_date?->format('d M Y'),
            'postedAt' => $j->posted_date?->format('d M Y'),
            'applyUrl' => $j->apply_url,
            'email' => $j->contact_email,
            'logo' => $j->employerPartner?->logo_path ?? null,
            'initials' => collect(explode(' ', (string) $j->company_name))->take(2)->map(fn ($w) => strtoupper($w[0] ?? ''))->implode(''),
        ])->all();

        return $this->render($request, 'Home/Alumni/Karir', ['totalJobs' => $totalJobs, 'jobs' => $jobs]);
    }

    private function render(Request $request, string $component, array $props = []): Response
    {
        $user = $request->user();
        $dashboardRoute = $user ? $user->prefix.'dashboard.index' : null;
        $dashboardUrl = $dashboardRoute && Route::has($dashboardRoute) ? route($dashboardRoute) : route('auth.select-role');

        return Inertia::render($component, array_merge([
            'campus' => [
                'name' => Campus::value('name') ?? config('app.name', 'NexaCampus'),
                'logo' => Campus::value('logo_horizontal') ?? asset('storage/images/default/logo-horizontal.png'),
                'description' => System::value('app_description') ?? 'Sistem informasi akademik perguruan tinggi terpadu.',
            ],
            'user' => PublicUser::make($user, $dashboardUrl),
            'links' => [
                'login' => route('auth.signin-index'),
                'admission' => route('root.admission.apply'),
                'admissionStatus' => route('root.admission.status'),
                'tuition' => route('root.admission.tuition'),
                'requirements' => route('root.admission.requirements'),
                'faq' => route('root.faq'),
                'contact' => route('root.kontak'),
                'announcements' => route('root.publication.announcements'),
            ],
        ], $props));
    }
}
