<?php

use App\Enums\CampaignStatus;
use App\Enums\EmploymentStatus;
use App\Enums\EventType;
use App\Enums\JobType;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\AlumniEventParticipant;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\JobPosting;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public ?AlumniProfile $profile = null;
    public int $profileCompleteness = 0;
    public array $profileInfo = [];
    public array $stats = [];
    public array $careerInfo = [];
    public $upcomingEvents;
    public $recentJobs;
    public $activeCampaigns;
    public $completedCampaigns;
    public int $totalJobsAvailable = 0;
    public int $totalUpcomingEvents = 0;
    public ?string $lastLoginAt = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->lastLoginAt = $user?->last_login_at?->format('d M Y H:i');

        if (! $user) {
            return;
        }

        $this->profile = AlumniProfile::query()
            ->where('user_id', $user->id)
            ->with(['studyProgram.faculty', 'faculty'])
            ->first();

        if (! $this->profile) {
            return;
        }

        $this->hasProfile = true;
        $this->profileCompleteness = $this->profile->profileCompleteness();

        $this->profileInfo = [
            'name' => $this->profile->full_name,
            'nim' => $this->profile->nim,
            'study_program' => $this->profile->studyProgram?->name ?? '-',
            'faculty' => $this->profile->faculty?->name ?? $this->profile->studyProgram?->faculty?->name ?? '-',
            'graduation_year' => $this->profile->graduation_year,
            'gpa' => $this->profile->gpa ? number_format((float) $this->profile->gpa, 2) : '-',
        ];

        $es = EmploymentStatus::tryFrom($this->profile->employment_status);

        $this->careerInfo = [
            'employment_status' => $es ? $es->label() : '-',
            'employer' => $this->profile->employer_name ?: '-',
            'job_title' => $this->profile->job_title ?: '-',
            'industry' => $this->profile->job_industry ?: '-',
            'city' => $this->profile->current_city ?: '-',
            'province' => $this->profile->current_province ?: '-',
            'linkedin' => $this->profile->linkedin_url ?: null,
        ];

        $eventsAttended = $this->profile->eventParticipants()->whereNotNull('attended_at')->count();
        $eventsRegistered = $this->profile->eventParticipants()->whereNull('attended_at')->count();
        $tracerFilled = $this->profile->tracerStudyResponses()->count();

        $this->stats = [
            'events_attended' => (int) $eventsAttended,
            'events_registered' => (int) $eventsRegistered,
            'tracer_studies_filled' => (int) $tracerFilled,
            'profile_completeness' => $this->profileCompleteness,
        ];

        $this->totalJobsAvailable = JobPosting::query()
            ->where('is_active', true)
            ->where('deadline_date', '>=', now()->toDateString())
            ->count();

        $this->totalUpcomingEvents = AlumniEvent::query()
            ->where('is_published', true)
            ->where('event_date', '>=', now())
            ->count();

        $this->upcomingEvents = AlumniEvent::query()
            ->where('is_published', true)
            ->where('event_date', '>=', now())
            ->orderBy('event_date')
            ->limit(5)
            ->get();

        $this->recentJobs = JobPosting::query()
            ->where('is_active', true)
            ->where('deadline_date', '>=', now()->toDateString())
            ->orderByDesc('posted_date')
            ->limit(5)
            ->get();

        $this->activeCampaigns = TracerStudyCampaign::query()
            ->where('status', 'active')
            ->whereDoesntHave('responses', fn ($q) => $q->where('alumni_profile_id', $this->profile->id))
            ->get();

        $this->completedCampaigns = TracerStudyCampaign::query()
            ->whereHas('responses', fn ($q) => $q->where('alumni_profile_id', $this->profile->id))
            ->latest('id')
            ->limit(3)
            ->get();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Dashboard',
            'pages' => 'Alumni Dashboard',
        ]);
    }

    public function employmentStatusBadge(): string
    {
        return match ($this->profile?->employment_status) {
            'working' => 'bg-green-lt text-green',
            'entrepreneur' => 'bg-blue-lt text-blue',
            'studying' => 'bg-indigo-lt text-indigo',
            'unemployed' => 'bg-yellow-lt text-yellow',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function completenessColor(): string
    {
        if ($this->profileCompleteness >= 75) {
            return '#10b981';
        }

        if ($this->profileCompleteness >= 50) {
            return '#f59e0b';
        }

        return '#ef4444';
    }
};
?>

@push('styles')
    <style>
        .modern-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            background: white;
        }

        .modern-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .stat-card {
            padding: 1.5rem;
            border-radius: 16px;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            line-height: 1;
        }

        .quick-action-btn {
            padding: 1.5rem;
            border-radius: 16px;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }

        .quick-action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            border-color: #667eea;
        }

        .info-badge {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .career-widget {
            border-radius: 20px;
            border: none;
            overflow: hidden;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .career-widget-header {
            background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem 1.25rem;
        }

        .career-line {
            padding: 1rem;
            border-radius: 14px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #eef2ff;
            transition: all 0.25s ease;
        }

        .career-line:hover {
            transform: translateX(4px);
            border-color: #c7d2fe;
            box-shadow: 0 8px 22px rgba(102, 126, 234, 0.12);
        }

        .event-item {
            padding: 1rem;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .event-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }

        .job-item {
            padding: 1rem;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .job-item:hover {
            background: #f1f5f9;
        }

        .completeness-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            position: relative;
        }

        .completeness-circle-inner {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil alumni belum terhubung. Hubungi administrator untuk mengaktifkan akun alumni kamu.</div>
    @else
        {{-- Hero Section --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                                {{ strtoupper(substr($profileInfo['name'] ?? 'A', 0, 1)) }}
                            </div>

                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Portal Alumni NexaCampus</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">{{ $profileInfo['name'] }}</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">
                                    <i class="fas fa-graduation-cap me-2"></i>{{ $profileInfo['study_program'] }} &bull; {{ $profileInfo['faculty'] }}
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <span class="info-badge">
                                        <i class="fas fa-id-card me-2"></i>NIM {{ $profileInfo['nim'] }}
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-calendar me-2"></i>Lulus {{ $profileInfo['graduation_year'] }}
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-star me-2"></i>IPK {{ $profileInfo['gpa'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Status Kerja</div>
                                    <span class="badge bg-white text-primary" style="font-size: 0.85rem;">
                                        {{ $careerInfo['employment_status'] }}
                                    </span>
                                </div>
                            </div>

                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Profil</div>
                                    <span class="badge {{ $profileCompleteness >= 75 ? 'bg-green-lt text-green' : ($profileCompleteness >= 50 ? 'bg-yellow-lt text-yellow' : 'bg-red-lt text-red') }}" style="font-size: 0.85rem;">
                                        {{ $profileCompleteness }}% Lengkap
                                    </span>
                                </div>
                            </div>

                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Event Diikuti</div>
                                    <div style="font-weight: 600; font-size: 0.85rem;">{{ $stats['events_attended'] }}</div>
                                </div>
                            </div>

                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Login Terakhir</div>
                                    <div style="font-weight: 600; font-size: 0.85rem;">{{ $lastLoginAt ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="{{ route('alumni.profile.edit') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-pen-to-square" style="color: #3b82f6;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Edit Profil</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Perbarui data karir dan kontak kamu</div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('alumni.jobs.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-briefcase" style="color: #8b5cf6;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Job Board</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Cari {{ $totalJobsAvailable }} lowongan kerja tersedia</div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('alumni.events.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-calendar-days" style="color: #10b981;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Event Alumni</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">{{ $totalUpcomingEvents }} event mendatang untuk kamu</div>
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="{{ route('alumni.tracer-study.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-chart-bar" style="color: #f59e0b;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Tracer Study</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Isi survei untuk akreditasi kampus</div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('alumni.profile.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-user-circle" style="color: #ef4444;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Profil Saya</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Lihat detail profil alumni kamu</div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('alumni.events.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-handshake" style="color: #06b6d4;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Networking</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Terhubung dengan sesama alumni</div>
                </a>
            </div>
        </div>

        {{-- Active Tracer Study Campaigns --}}
        @if ($activeCampaigns->isNotEmpty())
            <div class="card career-widget mb-4">
                <div class="career-widget-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                                <i class="fas fa-clipboard-question"></i>
                            </div>
                            <div>
                                <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;">Tracer Study Perlu Diisi</h3>
                                <div class="small text-secondary">Bantu kampus dengan mengisi survei tracer study berikut.</div>
                            </div>
                        </div>
                        <a href="{{ route('alumni.tracer-study.index') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-list me-1"></i> Lihat Semua
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @foreach ($activeCampaigns as $campaign)
                            <div class="career-line">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-bold" style="color: #1f2937;">{{ $campaign->title }}</div>
                                        <div class="text-secondary small mt-1">
                                            <i class="fas fa-calendar me-1"></i>Periode: {{ $campaign->start_date->format('d M Y') }} - {{ $campaign->end_date->format('d M Y') }}
                                        </div>
                                    </div>
                                    <a href="{{ route('alumni.tracer-study.fill', ['id' => $campaign->id]) }}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-pen me-1"></i> Isi Survei
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Stats Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-label">Kelengkapan Profil</div>
                    <div class="stat-value">{{ $stats['profile_completeness'] }}%</div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white;">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="stat-label">Lowongan Tersedia</div>
                    <div class="stat-value">{{ $totalJobsAvailable }}</div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-label">Event Diikuti</div>
                    <div class="stat-value">{{ $stats['events_attended'] }}</div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stat-label">Tracer Study Diisi</div>
                    <div class="stat-value">{{ $stats['tracer_studies_filled'] }}</div>
                </div>
            </div>
        </div>

        {{-- Career Summary & Profile Completeness --}}
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card modern-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-briefcase me-2" style="color: #667eea;"></i>Ringkasan Karir</h3>
                        <a href="{{ route('alumni.profile.edit') }}" class="btn btn-outline-primary" style="border-radius: 8px;">Perbarui</a>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div style="padding: 1.25rem; border-radius: 12px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); height: 100%;">
                                    <div style="font-size: 0.85rem; color: #065f46; margin-bottom: 0.5rem; font-weight: 500;">Status Pekerjaan</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #065f46;">{{ $careerInfo['employment_status'] }}</div>
                                    @if ($careerInfo['employer'] !== '-')
                                        <div style="font-size: 0.85rem; color: #047857; margin-top: 0.5rem;">{{ $careerInfo['employer'] }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div style="padding: 1.25rem; border-radius: 12px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); height: 100%;">
                                    <div style="font-size: 0.85rem; color: #1e40af; margin-bottom: 0.5rem; font-weight: 500;">Posisi / Jabatan</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #1e40af;">{{ $careerInfo['job_title'] }}</div>
                                    @if ($careerInfo['industry'] !== '-')
                                        <div style="font-size: 0.85rem; color: #3b82f6; margin-top: 0.5rem;">{{ $careerInfo['industry'] }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div style="padding: 1.25rem; border-radius: 12px; background: #f8fafc; height: 100%;">
                                    <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem; font-weight: 500;">Domisili</div>
                                    <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;">{{ $careerInfo['city'] }}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280; margin-top: 0.5rem;">{{ $careerInfo['province'] }}</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div style="padding: 1.25rem; border-radius: 12px; background: #f8fafc; height: 100%;">
                                    <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem; font-weight: 500;">Program Studi</div>
                                    <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;">{{ $profileInfo['study_program'] }}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280; margin-top: 0.5rem;">Lulus {{ $profileInfo['graduation_year'] }} &middot; IPK {{ $profileInfo['gpa'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card modern-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-user-check me-2" style="color: {{ $this->completenessColor() }};"></i>Kelengkapan Profil</h3>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div class="completeness-circle" style="background: conic-gradient({{ $this->completenessColor() }} {{ $profileCompleteness }}%, #e5e7eb 0%);">
                            <div class="completeness-circle-inner">
                                <div style="font-size: 1.75rem; font-weight: 700; color: {{ $this->completenessColor() }};">{{ $profileCompleteness }}%</div>
                                <div style="font-size: 0.75rem; color: #6b7280;">Lengkap</div>
                            </div>
                        </div>

                        <div class="text-secondary small mb-3">
                            @if ($profileCompleteness >= 75)
                                Profil kamu sudah cukup lengkap!
                            @elseif ($profileCompleteness >= 50)
                                Lengkapi profil untuk meningkatkan visibilitas.
                            @else
                                Profil belum lengkap. Segera perbarui data kamu.
                            @endif
                        </div>

                        @if ($profileCompleteness < 100)
                            <a href="{{ route('alumni.profile.edit') }}" class="btn btn-primary w-100" style="border-radius: 12px;">
                                <i class="fas fa-pen me-1"></i> Lengkapi Profil
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Upcoming Events & Recent Jobs --}}
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card modern-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-calendar-days me-2" style="color: #f59e0b;"></i>Event Mendatang</h3>
                        <a href="{{ route('alumni.events.index') }}" class="btn btn-outline-primary" style="border-radius: 8px;">Lihat Semua</a>
                    </div>

                    <div class="card-body p-4">
                        @if ($upcomingEvents->isEmpty())
                            <div class="p-4 text-center text-secondary">
                                <i class="fas fa-calendar-xmark" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                                Belum ada event mendatang.
                            </div>
                        @else
                            @foreach ($upcomingEvents as $event)
                                <a href="{{ route('alumni.events.show', ['id' => $event->id]) }}" class="text-decoration-none">
                                    <div class="event-item">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $event->title }}</div>
                                                <div style="font-size: 0.85rem; color: #6b7280;">
                                                    <i class="fas fa-clock me-1" style="color: #3b82f6;"></i>{{ $event->event_date->format('d M Y, H:i') }}
                                                    @if ($event->is_online)
                                                        <span class="mx-1">&middot;</span>
                                                        <span class="badge bg-blue-lt text-blue"><i class="fas fa-wifi me-1"></i>Online</span>
                                                    @elseif ($event->location)
                                                        <span class="mx-1">&middot;</span>
                                                        <i class="fas fa-map-marker-alt me-1" style="color: #ef4444;"></i>{{ $event->location }}
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="text-end">
                                                @php $et = EventType::tryFrom($event->event_type); @endphp
                                                <div class="badge bg-primary-lt text-primary mb-2" style="font-size: 0.85rem; padding: 0.5rem 0.75rem;">
                                                    {{ $et ? $et->label() : $event->event_type }}
                                                </div>
                                                @if ($event->max_participants)
                                                    <div style="font-size: 0.75rem; color: #9ca3af;">
                                                        <i class="fas fa-users me-1"></i>Max {{ $event->max_participants }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card modern-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-briefcase me-2" style="color: #8b5cf6;"></i>Lowongan Terbaru</h3>
                        <a href="{{ route('alumni.jobs.index') }}" class="btn btn-outline-primary" style="border-radius: 8px;">Lihat Semua</a>
                    </div>

                    <div class="card-body p-4">
                        @if ($recentJobs->isEmpty())
                            <div class="p-4 text-center text-secondary">
                                <i class="fas fa-briefcase" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                                Belum ada lowongan tersedia.
                            </div>
                        @else
                            @foreach ($recentJobs as $job)
                                <a href="{{ route('alumni.jobs.show', ['id' => $job->id]) }}" class="text-decoration-none">
                                    <div class="job-item">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $job->title }}</div>
                                                <div style="font-size: 0.85rem; color: #6b7280;">
                                                    <i class="fas fa-building me-1" style="color: #8b5cf6;"></i>{{ $job->company_name }}
                                                    <span class="mx-1">&middot;</span>
                                                    <i class="fas fa-map-marker-alt me-1" style="color: #ef4444;"></i>{{ $job->location ?? 'Remote' }}
                                                </div>
                                            </div>

                                            <div class="text-end">
                                                @php $jt = JobType::tryFrom($job->job_type); @endphp
                                                <div class="badge bg-green-lt text-green mb-2" style="font-size: 0.85rem; padding: 0.5rem 0.75rem;">
                                                    {{ $jt ? $jt->label() : ($job->job_type ?? '-') }}
                                                </div>
                                                <div style="font-size: 0.75rem; color: #9ca3af;">
                                                    <i class="fas fa-hourglass-half me-1"></i>{{ $job->deadline_date->format('d M Y') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Completed Tracer Studies --}}
        @if ($completedCampaigns->isNotEmpty())
            <div class="card modern-card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-check-circle me-2" style="color: #10b981;"></i>Tracer Study Selesai</h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach ($completedCampaigns as $campaign)
                            <div class="col-md-4">
                                <div class="career-line">
                                    <div class="d-flex align-items-start gap-2">
                                        <div style="width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-check" style="color: white; font-size: 0.8rem;"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold" style="color: #1f2937; font-size: 0.9rem;">{{ $campaign->title }}</div>
                                            <div class="text-secondary small mt-1">Periode {{ $campaign->start_date->format('d M') }} - {{ $campaign->end_date->format('d M Y') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
