<?php

use App\Enums\JobType;
use App\Models\Alumni\JobPosting;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterType = '';
    public string $filterIndustry = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterIndustry(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $jobs = JobPosting::query()
            ->where('is_active', true)
            ->where('deadline_date', '>=', now()->toDateString())
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('title', 'like', "%{$this->search}%")
                   ->orWhere('company_name', 'like', "%{$this->search}%")
                   ->orWhere('location', 'like', "%{$this->search}%");
            }))
            ->when($this->filterType, fn ($q) => $q->where('job_type', $this->filterType))
            ->when($this->filterIndustry, fn ($q) => $q->where('industry', 'like', "%{$this->filterIndustry}%"))
            ->orderByDesc('posted_date')
            ->paginate(12);

        return $this->view()->layout('layouts.app', [
            'menus' => 'Lowongan Kerja',
            'pages' => 'Job Board',
        ])->with('jobs', $jobs);
    }
};
?>

<div>
    <x-alert />

    {{-- Hero --}}
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 16px;">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-start gap-3">
                <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div>
                    <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Portal Alumni</div>
                    <h1 class="h2 mb-2" style="font-weight: 800;">Job Board</h1>
                    <div style="opacity: 0.9;">Temukan lowongan kerja dari mitra perusahaan kami.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari lowongan, perusahaan, atau lokasi...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="filterType" class="form-select">
                        <option value="">Semua Tipe</option>
                        @foreach (JobType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" wire:model.live.debounce.300ms="filterIndustry" class="form-control" placeholder="Industri...">
                </div>
                <div class="col-md-1">
                    <button wire:click="$set('search', ''); $set('filterType', ''); $set('filterIndustry', '')" class="btn btn-outline-secondary w-100" title="Reset">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Job Listings --}}
    @if ($jobs->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                <h4>Tidak ada lowongan ditemukan</h4>
                <p class="text-muted">Coba ubah filter pencarian kamu.</p>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($jobs as $job)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('alumni.jobs.show', ['id' => $job->id]) }}" class="text-decoration-none h-100 d-block">
                        <div class="card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-primary-lt text-primary">
                                        @php $jt = JobType::tryFrom($job->job_type); @endphp
                                        {{ $jt ? $jt->label() : ($job->job_type ?? '-') }}
                                    </span>
                                    <span class="badge bg-green-lt text-green small">
                                        <i class="fas fa-clock me-1"></i>{{ $job->deadline_date->diffForHumans() }}
                                    </span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">{{ $job->title }}</h5>
                                <div class="text-muted small mb-2">
                                    <i class="fas fa-building me-1"></i>{{ $job->company_name }}
                                </div>
                                <div class="text-muted small mb-2">
                                    <i class="fas fa-location-dot me-1"></i>{{ $job->location ?? 'Remote' }}
                                </div>
                                @if ($job->industry)
                                    <div class="text-muted small">
                                        <i class="fas fa-industry me-1"></i>{{ $job->industry }}
                                    </div>
                                @endif
                                @if ($job->salary_range)
                                    <div class="text-muted small mt-1">
                                        <i class="fas fa-money-bill-wave me-1"></i>{{ $job->salary_range }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $jobs->links() }}
        </div>
    @endif
</div>
