<?php

namespace App\Http\Controllers\Alumni;

use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Models\Alumni\JobPosting;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Job board alumni (portal) — hanya lowongan aktif yang belum melewati
 * tenggat. Lowongan kedaluwarsa tetap bisa dibuka lewat tautan langsung,
 * tetapi ditandai sudah ditutup dan tombol melamar disembunyikan.
 */
class JobBoardController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'job_type' => ['nullable', Rule::in(array_keys(JobType::options()))],
            'industry' => 'nullable|string|max:100',
        ]);

        $jobs = JobPosting::query()
            ->where('is_active', true)
            ->whereDate('deadline_date', '>=', today())
            ->when($validated['q'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            }))
            ->when($validated['job_type'] ?? null, fn ($query, $type) => $query->where('job_type', $type))
            ->when($validated['industry'] ?? null, fn ($query, $industry) => $query->where('industry', 'like', "%{$industry}%"))
            ->orderByDesc('posted_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Alumni/Jobs/Index', [
            'shell' => ShellProps::make($request->user(), 'Lowongan Kerja', 'Job Board'),
            'data' => [
                'rows' => collect($jobs->items())->values()->map(fn (JobPosting $job) => [
                    'id' => $job->id,
                    'title' => $job->title,
                    'companyName' => $job->company_name,
                    'industry' => $job->industry,
                    'location' => $job->location,
                    'salaryRange' => $job->salary_range,
                    'jobType' => $job->job_type,
                    'jobTypeLabel' => JobType::tryFrom((string) $job->job_type)?->label() ?? $job->job_type,
                    'postedDate' => $job->posted_date?->format('d M Y'),
                    'deadlineDate' => $job->deadline_date?->format('d M Y'),
                    'deadlineIn' => $job->deadline_date?->diffForHumans(),
                    'url' => route('alumni.jobs.show', $job),
                ])->all(),
                'currentPage' => $jobs->currentPage(),
                'lastPage' => $jobs->lastPage(),
                'perPage' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'job_type' => $validated['job_type'] ?? '',
                'industry' => $validated['industry'] ?? '',
            ],
            'jobTypeOptions' => JobType::options(),
            'urls' => [
                'index' => route('alumni.jobs.index'),
            ],
        ]);
    }

    public function show(Request $request, JobPosting $job): Response
    {
        abort_unless($job->is_active, 404);

        $job->load('employerPartner:id,name,description,website,logo_path');

        $isExpired = $job->deadline_date !== null && $job->deadline_date->lt(today());

        return Inertia::render('Alumni/Jobs/Show', [
            'shell' => ShellProps::make($request->user(), 'Lowongan Kerja', $job->title),
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'companyName' => $job->company_name,
                'industry' => $job->industry,
                'description' => $job->description,
                'requirements' => $job->requirements,
                'location' => $job->location,
                'jobType' => $job->job_type,
                'jobTypeLabel' => JobType::tryFrom((string) $job->job_type)?->label() ?? $job->job_type,
                'salaryRange' => $job->salary_range,
                'applyUrl' => $job->apply_url,
                'contactEmail' => $job->contact_email,
                'postedDate' => $job->posted_date?->format('d M Y'),
                'deadlineDate' => $job->deadline_date?->format('d M Y'),
                'deadlineIn' => $job->deadline_date?->diffForHumans(),
                'isExpired' => $isExpired,
                'partner' => $job->employerPartner ? [
                    'name' => $job->employerPartner->name,
                    'description' => $job->employerPartner->description,
                    'website' => $job->employerPartner->website,
                    'logoUrl' => $job->employerPartner->logo_path
                        ? asset('storage/'.$job->employerPartner->logo_path)
                        : null,
                ] : null,
            ],
            'urls' => [
                'index' => route('alumni.jobs.index'),
            ],
        ]);
    }
}
