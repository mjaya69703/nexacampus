<?php

namespace App\Http\Controllers\Home;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Assignment;
use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\Curriculum;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Support\Inertia\PublicUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AcademicPageController extends \App\Http\Controllers\Controller
{
    public function programs(Request $request): Response
    {
        $faculties = Faculty::query()
            ->where('is_active', true)
            ->with(['studyPrograms' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'shortName' => $faculty->short_name,
                'description' => $faculty->desc,
                'programs' => $faculty->studyPrograms->map(fn (StudyProgram $program) => [
                    'id' => $program->id,
                    'name' => $program->name,
                    'code' => $program->code,
                    'degree' => $program->degree,
                    'prefixDegree' => $program->prefix_degree,
                    'suffixDegree' => $program->suffix_degree,
                    'description' => $program->desc,
                    'duration' => in_array($program->degree, ['S1', 'D4'], true) ? 8 : ($program->degree === 'D3' ? 6 : 4),
                    'credits' => in_array($program->degree, ['S1', 'D4'], true) ? 144 : ($program->degree === 'D3' ? 110 : 72),
                ])->values()->all(),
            ]);

        return $this->render($request, 'Home/Academic/Programs', [
            'faculties' => $faculties->values()->all(),
            'totalPrograms' => $faculties->sum(fn (array $faculty) => count($faculty['programs'])),
            'admissionOpen' => AdmissionPeriod::where('is_active', true)->where('is_published', true)->exists(),
        ]);
    }

    public function calendar(Request $request): Response
    {
        $activeYear = AcademicYear::query()
            ->where('is_active', true)
            ->with(['academicPeriods' => fn ($query) => $query->orderBy('start_at')])
            ->first();

        return $this->render($request, 'Home/Academic/Calendar', [
            'year' => $activeYear ? [
                'name' => $activeYear->name,
                'semester' => $activeYear->semester,
                'startDate' => $activeYear->start_date?->format('d M Y'),
                'endDate' => $activeYear->end_date?->format('d M Y'),
                'startIso' => $activeYear->start_date?->toDateString(),
                'endIso' => $activeYear->end_date?->toDateString(),
            ] : null,
            'periods' => $activeYear?->academicPeriods->map(fn ($period) => [
                'id' => $period->id,
                'name' => $period->name,
                'type' => $period->type ?: 'Agenda akademik',
                'startDate' => $period->start_at?->format('d M Y'),
                'endDate' => $period->end_at?->format('d M Y'),
                'startIso' => $period->start_at?->toDateString(),
                'endIso' => $period->end_at?->toDateString(),
                'isActive' => (bool) $period->is_active,
                'description' => $period->desc,
            ])->values()->all() ?? [],
        ]);
    }

    public function schedule(Request $request): Response
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $schedules = CourseSchedule::query()
            ->where('is_active', true)
            ->with(['courseOffering.course', 'courseOffering.studyProgram', 'room', 'lecturerProfile'])
            ->get()
            ->map(fn (CourseSchedule $schedule) => [
                'id' => $schedule->id,
                'day' => $schedule->day_of_week,
                'courseCode' => $schedule->courseOffering?->course?->code ?? '-',
                'courseName' => $schedule->courseOffering?->course?->name ?? 'Mata kuliah belum ditentukan',
                'program' => $schedule->courseOffering?->studyProgram?->short_name ?? $schedule->courseOffering?->studyProgram?->name ?? '-',
                'lecturer' => $schedule->lecturerProfile?->full_name ?? 'Dosen belum ditentukan',
                'startTime' => $schedule->start_time?->format('H:i') ?? '--:--',
                'endTime' => $schedule->end_time?->format('H:i') ?? '--:--',
                'room' => $schedule->room?->name ?? 'Ruang belum ditentukan',
                'deliveryMode' => strtolower($schedule->delivery_mode ?: 'offline'),
                'sessionType' => $schedule->session_type ?: 'Lecture',
            ])
            ->sortBy(fn (array $schedule) => array_search($schedule['day'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], true) * 10000 + (int) str_replace(':', '', $schedule['startTime']))
            ->values()
            ->all();

        return $this->render($request, 'Home/Academic/Schedule', [
            'yearName' => $activeYear?->name,
            'schedules' => $schedules,
        ]);
    }

    public function curriculum(Request $request): Response
    {
        $curriculums = Curriculum::query()
            ->where('is_active', true)
            ->with(['studyProgram', 'curriculumCourses.course'])
            ->orderBy('study_program_id')
            ->orderByDesc('start_year')
            ->get()
            ->map(fn (Curriculum $curriculum) => [
                'id' => $curriculum->id,
                'program' => $curriculum->studyProgram?->name ?? 'Umum',
                'programCode' => $curriculum->studyProgram?->code,
                'name' => $curriculum->name,
                'code' => $curriculum->code,
                'startYear' => $curriculum->start_year,
                'endYear' => $curriculum->end_year,
                'description' => $curriculum->desc,
                'courseCount' => $curriculum->curriculumCourses->where('is_active', true)->count(),
                'credits' => $curriculum->curriculumCourses->where('is_active', true)->sum(fn ($course) => $course->credits_override ?: $course->course?->credits ?: 0),
            ])
            ->values()
            ->all();

        return $this->render($request, 'Home/Academic/Curriculum', [
            'curriculums' => $curriculums,
        ]);
    }

    public function elearning(Request $request): Response
    {
        $activeOfferings = CourseOffering::whereIn('status', ['Active', 'Published', 'Open'])->count();
        $materials = CourseMaterial::where('is_published', true)->count();
        $assignments = Assignment::where('is_published', true)->count();

        return $this->render($request, 'Home/Academic/Elearning', [
            'stats' => [
                'activeOfferings' => $activeOfferings,
                'materials' => $materials,
                'assignments' => $assignments,
            ],
        ]);
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
