<?php

use Livewire\Component;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\JobPosting;
use App\Models\Academic\StudyProgram;

new class extends Component
{
    public array $stats = [];
    public array $alumni = [];
    public array $jobPostings = [];

    public function mount(): void
    {
        $this->stats = [
            'total'      => AlumniProfile::where('is_active', true)->count(),
            'employed'   => AlumniProfile::where('is_active', true)->where('employment_status', 'employed')->count(),
            'programs'   => StudyProgram::where('is_active', true)->count(),
            'jobs'       => JobPosting::where('is_active', true)->whereDate('deadline_date', '>=', now())->count(),
        ];

        $this->alumni = AlumniProfile::with(['studyProgram', 'faculty'])
            ->where('is_active', true)
            ->whereNotNull('employment_status')
            ->latest('graduation_date')
            ->limit(9)
            ->get()
            ->map(fn($a) => [
                'name'            => $a->full_name,
                'nim'             => $a->nim,
                'program'         => $a->studyProgram?->name ?? '-',
                'faculty'         => $a->faculty?->short_name ?? '-',
                'graduation_year' => $a->graduation_year,
                'gpa'             => $a->gpa,
                'employer'        => $a->employer_name,
                'job_title'       => $a->job_title,
                'city'            => $a->current_city,
                'employment_status' => $a->employment_status,
                'linkedin'        => $a->linkedin_url,
                'initials'        => collect(explode(' ', $a->full_name))->take(2)->map(fn($w) => strtoupper($w[0] ?? ''))->implode(''),
            ])->toArray();

        $this->jobPostings = JobPosting::where('is_active', true)
            ->whereDate('deadline_date', '>=', now())
            ->orderBy('deadline_date')
            ->limit(6)
            ->get()
            ->map(fn($j) => [
                'id'          => $j->id,
                'title'       => $j->title,
                'company'     => $j->company_name,
                'location'    => $j->location,
                'job_type'    => $j->job_type,
                'industry'    => $j->industry,
                'salary'      => $j->salary_range,
                'deadline'    => $j->deadline_date?->format('d M Y'),
                'apply_url'   => $j->apply_url,
            ])->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Kemahasiswaan',
            'pages' => 'Alumni & Karir',
        ]);
    }
};
?>

@php
$empMap = [
    'employed'     => ['label'=>'Bekerja',    'class'=>'bg-success-lt text-success'],
    'self_employed'=> ['label'=>'Wirausaha',  'class'=>'bg-warning-lt text-warning'],
    'unemployed'   => ['label'=>'Mencari Kerja','class'=>'bg-secondary-lt text-secondary'],
    'studying'     => ['label'=>'Studi Lanjut','class'=>'bg-info-lt text-info'],
];
$avatarColors = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444','#06b6d4'];
@endphp

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-12">

                {{-- Hero --}}
                <div class="admission-hero mb-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker d-flex align-items-center gap-2 mb-2">
                                <span class="badge-pulse"></span>
                                <span>Jaringan Alumni NexaCampus</span>
                            </div>
                            <h1 class="admission-title mb-3">Alumni yang<br><span style="opacity:.8">Mengubah Dunia</span></h1>
                            <p class="admission-subtitle mb-4">
                                Ribuan lulusan NexaCampus kini berkarir di berbagai industri. Bergabunglah dengan komunitas alumni dan jelajahi peluang karir eksklusif.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.alumni.karir') }}" class="btn btn-light px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="fas fa-briefcase text-primary"></i> Lowongan Karir
                                </a>
                                <a href="{{ route('root.admission.apply') }}" class="btn btn-outline-light px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-paper-plane"></i> Daftar Sekarang
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Jaringan Alumni</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ number_format($stats['total']) }}+ Alumni</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">Aktif</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $stats['employed'] }}</span>
                                            <small class="text-white-50">Bekerja / Wirausaha</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $stats['jobs'] }}</span>
                                            <small class="text-white-50">Lowongan Aktif</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Stats bar --}}
                <div class="row g-3 mb-5">
                    @foreach([
                        ['icon'=>'fa-user-graduate','color'=>'#3b82f6','bg'=>'rgba(59,130,246,.1)','val'=>$stats['total'],'label'=>'Total Alumni'],
                        ['icon'=>'fa-briefcase','color'=>'#10b981','bg'=>'rgba(16,185,129,.1)','val'=>$stats['employed'],'label'=>'Bekerja / Wirausaha'],
                        ['icon'=>'fa-graduation-cap','color'=>'#8b5cf6','bg'=>'rgba(139,92,246,.1)','val'=>$stats['programs'],'label'=>'Program Studi'],
                        ['icon'=>'fa-file-lines','color'=>'#f59e0b','bg'=>'rgba(245,158,11,.1)','val'=>$stats['jobs'],'label'=>'Lowongan Aktif'],
                    ] as $s)
                    <div class="col-lg-3 col-sm-6">
                        <div class="admission-card border-0 rounded-3 shadow-sm p-4 d-flex align-items-center gap-3">
                            <div style="width:48px;height:48px;border-radius:14px;background:{{ $s['bg'] }};color:{{ $s['color'] }};display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                                <i class="fas {{ $s['icon'] }}"></i>
                            </div>
                            <div>
                                <div class="fw-black text-body" style="font-size:1.5rem;line-height:1;">{{ number_format($s['val']) }}</div>
                                <div class="text-muted" style="font-size:.8rem;">{{ $s['label'] }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Alumni grid --}}
                @if(count($alumni) > 0)
                <div class="mb-5">
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <div class="step-badge"><i class="fas fa-users"></i></div>
                        <h3 class="fw-bolder text-body mb-0" style="font-size:1.1rem;">Alumni Terkini</h3>
                    </div>
                    <div class="row g-3">
                        @foreach($alumni as $i => $a)
                        @php $emp = $empMap[$a['employment_status']] ?? ['label'=>$a['employment_status'],'class'=>'bg-secondary-lt text-secondary']; @endphp
                        <div class="col-lg-4 col-sm-6">
                            <div class="admission-card border-0 rounded-3 shadow-sm p-4 h-100 d-flex flex-column" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div style="width:48px;height:48px;border-radius:50%;background:{{ $avatarColors[$i % count($avatarColors)] }};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.95rem;flex-shrink:0;">
                                        {{ $a['initials'] }}
                                    </div>
                                    <div>
                                        <div class="fw-bolder text-body" style="font-size:.9rem;">{{ $a['name'] }}</div>
                                        <div class="text-muted" style="font-size:.75rem;">{{ $a['program'] }}</div>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge {{ $emp['class'] }} fw-semibold" style="font-size:.7rem;">{{ $emp['label'] }}</span>
                                    <span class="badge bg-secondary-lt text-secondary fw-semibold" style="font-size:.7rem;">{{ $a['graduation_year'] }}</span>
                                    @if($a['gpa'])<span class="badge bg-primary-lt text-primary fw-semibold" style="font-size:.7rem;">IPK {{ number_format($a['gpa'],2) }}</span>@endif
                                </div>
                                @if($a['job_title'] || $a['employer'])
                                <div class="mt-auto">
                                    <div class="text-body fw-semibold" style="font-size:.83rem;">{{ $a['job_title'] }}</div>
                                    <div class="text-muted" style="font-size:.78rem;">{{ $a['employer'] }}@if($a['city']) — {{ $a['city'] }}@endif</div>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Job postings preview --}}
                @if(count($jobPostings) > 0)
                <div class="p-5 rounded-4" style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h3 class="fw-bolder text-white mb-1" style="font-size:1.2rem;">Lowongan Karir Terbaru</h3>
                            <div class="text-white-50" style="font-size:.85rem;">Dari mitra industri & alumni NexaCampus</div>
                        </div>
                        <a href="{{ route('root.alumni.karir') }}" class="btn btn-outline-light btn-sm px-3 fw-bold">Lihat Semua</a>
                    </div>
                    <div class="row g-3">
                        @foreach(array_slice($jobPostings, 0, 3) as $job)
                        <div class="col-lg-4">
                            <div class="p-4 rounded-3" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <span class="badge bg-primary text-white fw-semibold" style="font-size:.7rem;">{{ $job['job_type'] }}</span>
                                    <span class="text-white-50" style="font-size:.72rem;">Deadline: {{ $job['deadline'] }}</span>
                                </div>
                                <div class="fw-bolder text-white mb-1" style="font-size:.92rem;">{{ $job['title'] }}</div>
                                <div class="text-white-50 mb-2" style="font-size:.8rem;">{{ $job['company'] }} · {{ $job['location'] }}</div>
                                @if($job['salary'])<div class="text-success fw-semibold" style="font-size:.8rem;"><i class="fas fa-coins me-1"></i>{{ $job['salary'] }}</div>@endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
