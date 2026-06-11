<?php

use App\Enums\CampaignStatus;
use App\Enums\EmploymentStatus;
use App\Enums\EventType;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\JobPosting;
use App\Models\Alumni\TracerStudyCampaign;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public ?AlumniProfile $profile = null;
    public int $profileCompleteness = 0;
    public array $stats = [];
    public $upcomingEvents;
    public $recentJobs;
    public $activeCampaigns;

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $this->profile = AlumniProfile::query()
            ->where('user_id', $user->id)
            ->with(['studyProgram', 'faculty'])
            ->first();

        if (! $this->profile) {
            return;
        }

        $this->hasProfile = true;
        $this->profileCompleteness = $this->profile->profileCompleteness();

        $this->stats = [
            'events_attended' => $this->profile->eventParticipants()->whereNotNull('attended_at')->count(),
            'events_registered' => $this->profile->eventParticipants()->whereNull('attended_at')->count(),
            'tracer_studies_filled' => $this->profile->tracerStudyResponses()->count(),
        ];

        $this->upcomingEvents = AlumniEvent::query()
            ->where('is_published', true)
            ->where('event_date', '>=', now())
            ->orderBy('event_date')
            ->limit(3)
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
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Dashboard',
            'pages' => 'Alumni Dashboard',
        ]);
    }
};
?>

<div>
    <x-alert />

    {{-- Hero Section --}}
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 16px;">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Portal Alumni</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">
                            {{ $hasProfile ? 'Halo, ' . $profile->full_name : 'Selamat Datang, Alumni!' }}
                        </h1>
                        <div style="opacity: 0.9;">
                            @if ($hasProfile)
                                {{ $profile->studyProgram?->name ?? '-' }} &middot; Lulus {{ $profile->graduation_year }}
                            @else
                                Lengkapi profil alumni kamu untuk mengakses semua fitur.
                            @endif
                        </div>
                    </div>
                </div>
                @if ($hasProfile)
                    <a href="{{ route('alumni.profile.edit') }}" class="btn btn-light fw-semibold">
                        <i class="fas fa-pen me-1"></i> Edit Profil
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (! $hasProfile)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                <h3>Profil Alumni Belum Tersedia</h3>
                <p class="text-muted">Hubungi administrator untuk mengaktifkan profil alumni kamu.</p>
            </div>
        </div>
    @else
        {{-- Stats Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span style="width: 48px; height: 48px; background: #e0e7ff; color: #4f46e5; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            <i class="fas fa-user-check"></i>
                        </span>
                        <div>
                            <div class="text-muted small">Profil Lengkap</div>
                            <div class="h3 mb-0 fw-bold">{{ $profileCompleteness }}%</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span style="width: 48px; height: 48px; background: #fef3c7; color: #d97706; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            <i class="fas fa-calendar-check"></i>
                        </span>
                        <div>
                            <div class="text-muted small">Event Diikuti</div>
                            <div class="h3 mb-0 fw-bold">{{ $stats['events_attended'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span style="width: 48px; height: 48px; background: #dcfce7; color: #16a34a; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            <i class="fas fa-chart-bar"></i>
                        </span>
                        <div>
                            <div class="text-muted small">Tracer Study</div>
                            <div class="h3 mb-0 fw-bold">{{ $stats['tracer_studies_filled'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span style="width: 48px; height: 48px; background: #fce7f3; color: #db2777; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            <i class="fas fa-briefcase"></i>
                        </span>
                        <div>
                            <div class="text-muted small">Status Kerja</div>
                            <div class="h5 mb-0 fw-bold">
                                @php $es = EmploymentStatus::tryFrom($profile->employment_status); @endphp
                                {{ $es ? $es->label() : '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Active Tracer Study Campaigns --}}
        @if ($activeCampaigns->isNotEmpty())
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <div>
                        <h3 class="card-title mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Tracer Study Perlu Diisi</h3>
                        <small class="text-muted">Bantu kampus dengan mengisi survei tracer study.</small>
                    </div>
                    <a href="{{ route('alumni.tracer-study.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @foreach ($activeCampaigns as $campaign)
                            <div class="border rounded p-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div>
                                    <div class="fw-bold">{{ $campaign->title }}</div>
                                    <div class="text-muted small">Periode: {{ $campaign->start_date->format('d M Y') }} - {{ $campaign->end_date->format('d M Y') }}</div>
                                </div>
                                <a href="{{ route('alumni.tracer-study.fill', ['id' => $campaign->id]) }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-pen me-1"></i> Isi Survei
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-4">
            {{-- Upcoming Events --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0"><i class="fas fa-calendar me-2 text-primary"></i>Event Mendatang</h3>
                        <a href="{{ route('alumni.events.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body p-4">
                        @if ($upcomingEvents->isEmpty())
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-xmark fa-2x mb-2 opacity-50"></i>
                                <div>Belum ada event mendatang.</div>
                            </div>
                        @else
                            <div class="d-grid gap-3">
                                @foreach ($upcomingEvents as $event)
                                    <a href="{{ route('alumni.events.show', ['id' => $event->id]) }}" class="text-decoration-none">
                                        <div class="border rounded p-3">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $event->title }}</div>
                                                    <div class="text-muted small mt-1">
                                                        <i class="fas fa-clock me-1"></i>{{ $event->event_date->format('d M Y, H:i') }}
                                                        @if ($event->is_online)
                                                            &middot; <span class="badge bg-blue-lt text-blue">Online</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                @php $et = EventType::tryFrom($event->event_type); @endphp
                                                <span class="badge bg-primary-lt text-primary">{{ $et ? $et->label() : $event->event_type }}</span>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Recent Jobs --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0"><i class="fas fa-briefcase me-2 text-primary"></i>Lowongan Terbaru</h3>
                        <a href="{{ route('alumni.jobs.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body p-4">
                        @if ($recentJobs->isEmpty())
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-briefcase fa-2x mb-2 opacity-50"></i>
                                <div>Belum ada lowongan tersedia.</div>
                            </div>
                        @else
                            <div class="d-grid gap-3">
                                @foreach ($recentJobs as $job)
                                    <a href="{{ route('alumni.jobs.show', ['id' => $job->id]) }}" class="text-decoration-none">
                                        <div class="border rounded p-3">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $job->title }}</div>
                                                    <div class="text-muted small">{{ $job->company_name }} &middot; {{ $job->location ?? 'Remote' }}</div>
                                                </div>
                                                <span class="badge bg-green-lt text-green small">{{ $job->deadline_date->format('d M') }}</span>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
