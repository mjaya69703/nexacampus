<?php

namespace App\Http\Controllers\Alumni;

use App\Enums\EmploymentStatus;
use App\Enums\EventType;
use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\JobPosting;
use App\Models\Alumni\TracerStudyCampaign;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->alumniProfile()->with(['studyProgram', 'faculty'])->first();

        if (! $profile) {
            return Inertia::render('Alumni/Dashboard', [
                'shell' => ShellProps::make($user, 'Dashboard', 'Dashboard Alumni'),
                'hasProfile' => false,
                'alumni' => ['name' => $user->name],
                'career' => null,
                'stats' => ['eventsJoined' => 0, 'eventsAttended' => 0, 'tracerFilled' => 0, 'completeness' => 0],
                'jobs' => ['total' => 0, 'items' => []],
                'events' => ['total' => 0, 'items' => []],
                'tracerActive' => [],
                'tracerDone' => [],
                'urls' => $this->urls(),
            ]);
        }

        $profileId = $profile->id;
        $completeness = $profile->profileCompleteness();

        $attended = $profile->eventParticipants()->whereNotNull('attended_at')->count();
        $registered = $profile->eventParticipants()->whereNull('attended_at')->count();
        $tracerFilled = $profile->tracerStudyResponses()->count();

        $jobsQuery = JobPosting::where('is_active', true)->where('deadline_date', '>=', now()->toDateString());
        $eventsQuery = AlumniEvent::where('is_published', true)->where('event_date', '>=', now());

        $tracerActive = TracerStudyCampaign::where('status', 'active')
            ->whereDoesntHave('responses', fn ($query) => $query->where('alumni_profile_id', $profileId))
            ->orderBy('end_date')
            ->get()
            ->map(fn ($campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'periodLabel' => $campaign->start_date->format('d M Y').' - '.$campaign->end_date->format('d M Y'),
                'url' => route('alumni.tracer-study.fill', $campaign),
            ])
            ->values()
            ->all();

        $tracerDone = TracerStudyCampaign::whereHas('responses', fn ($query) => $query->where('alumni_profile_id', $profileId))
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn ($campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'periodLabel' => $campaign->start_date->format('d M Y').' - '.$campaign->end_date->format('d M Y'),
                'url' => route('alumni.tracer-study.show', $campaign),
            ])
            ->values()
            ->all();

        return Inertia::render('Alumni/Dashboard', [
            'shell' => ShellProps::make($user, 'Dashboard', 'Dashboard Alumni'),
            'hasProfile' => true,
            'alumni' => [
                'name' => $profile->full_name ?: $user->name,
                'nim' => $profile->nim,
                'programName' => $profile->studyProgram?->name,
                'facultyName' => $profile->faculty?->name,
                'gradYear' => $profile->graduation_year,
                'gpa' => $profile->gpa !== null ? number_format((float) $profile->gpa, 2) : null,
                'completeness' => $completeness,
            ],
            'career' => [
                'statusLabel' => EmploymentStatus::tryFrom((string) $profile->employment_status)?->label() ?? ucwords((string) $profile->employment_status),
                'employer' => $profile->employer_name,
                'title' => $profile->job_title,
                'industry' => $profile->job_industry,
                'domicile' => collect([$profile->current_city, $profile->current_province])->filter()->implode(', ') ?: null,
            ],
            'stats' => [
                'eventsJoined' => $attended + $registered,
                'eventsAttended' => $attended,
                'tracerFilled' => $tracerFilled,
                'completeness' => $completeness,
            ],
            'jobs' => [
                'total' => (clone $jobsQuery)->count(),
                'items' => $jobsQuery->orderByDesc('posted_date')->limit(5)->get()->map(fn ($job) => [
                    'id' => $job->id,
                    'title' => $job->title,
                    'company' => $job->company_name,
                    'location' => $job->location,
                    'typeLabel' => JobType::tryFrom((string) $job->job_type)?->label() ?? $job->job_type,
                    'deadlineLabel' => $job->deadline_date->format('d M Y'),
                    'url' => route('alumni.jobs.show', $job),
                ])->values()->all(),
            ],
            'events' => [
                'total' => (clone $eventsQuery)->count(),
                'items' => $eventsQuery->orderBy('event_date')->limit(5)->get()->map(fn ($event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'typeLabel' => EventType::tryFrom((string) $event->event_type)?->label() ?? $event->event_type,
                    'dateLabel' => $event->event_date->format('d M Y H:i'),
                    'placeLabel' => $event->is_online ? 'Online' : ($event->location ?? '-'),
                    'url' => route('alumni.events.show', $event),
                ])->values()->all(),
            ],
            'tracerActive' => $tracerActive,
            'tracerDone' => $tracerDone,
            'urls' => $this->urls(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        return [
            'profile' => route('alumni.profile.index'),
            'profileEdit' => route('alumni.profile.edit'),
            'jobs' => route('alumni.jobs.index'),
            'events' => route('alumni.events.index'),
            'tracer' => route('alumni.tracer-study.index'),
        ];
    }
}
