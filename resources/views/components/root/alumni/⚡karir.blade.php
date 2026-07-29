<?php

use Livewire\Component;
use App\Models\Alumni\JobPosting;
use Illuminate\Support\Str;

new class extends Component
{
    public array $jobs = [];
    public int $totalJobs = 0;

    public function mount(): void
    {
        $query = JobPosting::with('employerPartner')
            ->where('is_active', true)
            ->whereDate('deadline_date', '>=', now())
            ->orderBy('deadline_date');

        $this->totalJobs = $query->count();

        $this->jobs = $query->get()->map(fn($j) => [
            'id'          => $j->id,
            'title'       => $j->title,
            'company'     => $j->company_name,
            'industry'    => $j->industry,
            'location'    => $j->location,
            'job_type'    => $j->job_type,
            'salary'      => $j->salary_range,
            'description' => Str::limit(strip_tags($j->description), 150),
            'requirements'=> Str::limit(strip_tags($j->requirements), 150),
            'deadline'    => $j->deadline_date?->format('d M Y'),
            'posted_at'   => $j->posted_date?->format('d M Y'),
            'apply_url'   => $j->apply_url,
            'email'       => $j->contact_email,
            'logo'        => $j->employerPartner?->logo_path ?? null,
            'initials'    => collect(explode(' ', $j->company_name))->take(2)->map(fn($w) => strtoupper($w[0] ?? ''))->implode(''),
        ])->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Kemahasiswaan',
            'pages' => 'Lowongan Karir',
        ]);
    }
};
?>

@php
$typeColors = [
    'full-time' => ['label' => 'Full-time', 'class' => 'bg-primary-lt text-primary'],
    'part-time' => ['label' => 'Part-time', 'class' => 'bg-warning-lt text-warning'],
    'contract'  => ['label' => 'Kontrak',   'class' => 'bg-info-lt text-info'],
    'internship'=> ['label' => 'Magang',    'class' => 'bg-success-lt text-success'],
    'freelance' => ['label' => 'Freelance', 'class' => 'bg-secondary-lt text-secondary'],
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
                                <span>Pusat Karir NexaCampus</span>
                            </div>
                            <h1 class="admission-title mb-3">Peluang Karir<br><span style="opacity:.8">Bagi Lulusan & Mahasiswa</span></h1>
                            <p class="admission-subtitle mb-4">
                                Temukan berbagai lowongan pekerjaan dan magang dari mitra industri kami. Jadikan langkah awal untuk karir cemerlang Anda.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.alumni.index') }}" class="btn btn-outline-light px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-users"></i> Profil Alumni
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Lowongan Aktif</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ $totalJobs }} Posisi</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">Hiring</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <i class="fas fa-building text-white-50"></i>
                                    <small class="text-white-50">Kesempatan eksklusif dari ratusan mitra industri kampus</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($jobs) > 0)
                <div class="row g-4">
                    @foreach($jobs as $i => $job)
                    @php $type = $typeColors[strtolower($job['job_type'])] ?? ['label'=>$job['job_type'],'class'=>'bg-secondary-lt text-secondary']; @endphp
                    <div class="col-lg-6">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-4 h-100 d-flex flex-column" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='none'">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <div style="width:56px;height:56px;border-radius:14px;background:{{ $avatarColors[$i % count($avatarColors)] }};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.1rem;flex-shrink:0;">
                                    {{ $job['initials'] }}
                                </div>
                                <div class="flex-fill">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <h4 class="fw-bolder text-body mb-1" style="font-size:1.1rem;line-height:1.3;">{{ $job['title'] }}</h4>
                                        <span class="badge {{ $type['class'] }} px-2 py-1 flex-shrink-0">{{ $type['label'] }}</span>
                                    </div>
                                    <div class="text-muted" style="font-size:.85rem;">{{ $job['company'] }}</div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @if($job['location'])
                                <span class="badge bg-light text-body border" style="font-weight:500;"><i class="fas fa-location-dot text-muted me-1"></i>{{ $job['location'] }}</span>
                                @endif
                                @if($job['industry'])
                                <span class="badge bg-light text-body border" style="font-weight:500;"><i class="fas fa-building text-muted me-1"></i>{{ $job['industry'] }}</span>
                                @endif
                                @if($job['salary'])
                                <span class="badge bg-success-lt text-success" style="font-weight:600;"><i class="fas fa-coins me-1"></i>{{ $job['salary'] }}</span>
                                @endif
                            </div>

                            <p class="text-muted mb-4 flex-fill" style="font-size:.85rem;line-height:1.6;">{{ $job['description'] }}</p>

                            <div class="mt-auto pt-3 border-top d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-muted" style="font-size:.75rem;">Deadline</div>
                                    <div class="fw-semibold text-danger" style="font-size:.85rem;"><i class="fas fa-clock me-1"></i>{{ $job['deadline'] }}</div>
                                </div>
                                @if($job['apply_url'])
                                <a href="{{ $job['apply_url'] }}" target="_blank" class="btn btn-primary px-4 fw-bold rounded-pill">
                                    Lamar Sekarang
                                </a>
                                @elseif($job['email'])
                                <a href="mailto:{{ $job['email'] }}" class="btn btn-primary px-4 fw-bold rounded-pill">
                                    Kirim Email
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-briefcase"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Belum Ada Lowongan</h4>
                    <p class="text-muted mb-0">Lowongan pekerjaan dan magang akan segera diperbarui oleh mitra kami.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
