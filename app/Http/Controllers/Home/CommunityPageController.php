<?php

namespace App\Http\Controllers\Home;

use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\JobPosting;
use App\Models\Financial\Scholarship;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Support\Inertia\PublicUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CommunityPageController extends \App\Http\Controllers\Controller
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
        return $this->render($request, 'Home/Kemahasiswaan/Organisasi');
    }

    public function prestasi(Request $request): Response
    {
        return $this->render($request, 'Home/Kemahasiswaan/Prestasi');
    }

    public function layanan(Request $request): Response
    {
        return $this->render($request, 'Home/Kemahasiswaan/Layanan');
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
