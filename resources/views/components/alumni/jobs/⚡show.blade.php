<?php

use App\Enums\JobType;
use App\Models\Alumni\JobPosting;
use Livewire\Component;

new class extends Component
{
    public JobPosting $job;

    public function mount($id): void
    {
        $this->job = JobPosting::query()
            ->where('is_active', true)
            ->with('employerPartner')
            ->findOrFail($id);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Lowongan Kerja',
            'pages' => $this->job->title,
        ]);
    }
};
?>

<div>
    <x-alert />

    <a href="{{ route('alumni.jobs.index') }}" class="btn btn-outline-secondary mb-4">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Job Board
    </a>

    <div class="row g-4">
        {{-- Main Content --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                        <div>
                            <h1 class="h2 fw-bold mb-1">{{ $job->title }}</h1>
                            <div class="text-muted">
                                <i class="fas fa-building me-1"></i>{{ $job->company_name }}
                                @if ($job->location)
                                    &middot; <i class="fas fa-location-dot me-1"></i>{{ $job->location }}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            @php $jt = JobType::tryFrom($job->job_type); @endphp
                            @if ($jt)
                                <span class="badge bg-primary-lt text-primary px-3 py-2">{{ $jt->label() }}</span>
                            @endif
                        </div>
                    </div>

                    <hr class="my-4">

                    <h4 class="fw-bold mb-3">Deskripsi Pekerjaan</h4>
                    <div class="mb-4" style="white-space: pre-wrap; line-height: 1.8;">{{ $job->description }}</div>

                    @if ($job->requirements)
                        <h4 class="fw-bold mb-3">Persyaratan</h4>
                        <div class="mb-4" style="white-space: pre-wrap; line-height: 1.8;">{{ $job->requirements }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3">Informasi Lowongan</h4>
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 45%;">Tipe</td>
                            <td class="fw-semibold">
                                @php $jt = JobType::tryFrom($job->job_type); @endphp
                                {{ $jt ? $jt->label() : ($job->job_type ?? '-') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Industri</td>
                            <td>{{ $job->industry ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Lokasi</td>
                            <td>{{ $job->location ?? 'Remote' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Gaji</td>
                            <td>{{ $job->salary_range ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Diposting</td>
                            <td>{{ $job->posted_date->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Deadline</td>
                            <td>
                                <span class="badge bg-warning-lt text-warning">{{ $job->deadline_date->format('d M Y') }}</span>
                            </td>
                        </tr>
                        @if ($job->contact_email)
                            <tr>
                                <td class="text-muted">Kontak</td>
                                <td><a href="mailto:{{ $job->contact_email }}" class="text-primary">{{ $job->contact_email }}</a></td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            @if ($job->apply_url)
                <a href="{{ $job->apply_url }}" target="_blank" class="btn btn-primary btn-lg w-100 mb-3">
                    <i class="fas fa-external-link me-2"></i> Lamar Sekarang
                </a>
            @else
                @if ($job->contact_email)
                    <a href="mailto:{{ $job->contact_email }}?subject=Lamaran: {{ $job->title }}" class="btn btn-primary btn-lg w-100 mb-3">
                        <i class="fas fa-paper-plane me-2"></i> Kirim Lamaran
                    </a>
                @endif
            @endif

            @if ($job->employerPartner)
                <div class="card">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-2">Tentang {{ $job->employerPartner->name }}</h5>
                        @if ($job->employerPartner->description)
                            <p class="text-muted small">{{ str($job->employerPartner->description)->limit(200) }}</p>
                        @endif
                        @if ($job->employerPartner->website)
                            <a href="{{ $job->employerPartner->website }}" target="_blank" class="text-primary small">
                                <i class="fas fa-globe me-1"></i>{{ $job->employerPartner->website }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
