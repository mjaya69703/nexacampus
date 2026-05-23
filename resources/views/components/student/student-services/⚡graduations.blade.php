<?php

use App\Models\StudentService\GraduationApplication;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;

    public $applications;

    public array $summary = ['total' => 0, 'in_progress' => 0, 'approved' => 0, 'finalized' => 0];

    public function mount(): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        $this->applications = collect();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->applications = GraduationApplication::query()
            ->where('student_profile_id', $studentProfile->id)
            ->orderByDesc('created_at')
            ->get();

        $this->summary = [
            'total' => $this->applications->count(),
            'in_progress' => $this->applications->whereIn('status', ['submitted', 'under_review', 'revision_requested'])->count(),
            'approved' => $this->applications->where('status', 'approved')->count(),
            'finalized' => $this->applications->where('status', 'finalized')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Graduation Applications',
        ]);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'finalized' => 'bg-green-lt text-green',
            'approved' => 'bg-blue-lt text-blue',
            'revision_requested' => 'bg-yellow-lt text-yellow',
            'under_review' => 'bg-indigo-lt text-indigo',
            'rejected', 'cancelled' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'finalized' => 'Final',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }
};
?>

@include('components.student.student-services.service-styles')

<div>
    <x-alert />

    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position: relative;">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Student Services</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Pengajuan Yudisium</h1>
                        <div style="opacity: 0.9; margin-bottom: 1rem;">Ajukan yudisium, pantau review akademik, dan lihat status finalisasi kelulusan.</div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="info-badge"><i class="fas fa-file-lines me-2"></i>{{ $summary['total'] }} pengajuan</span>
                            <span class="info-badge"><i class="fas fa-clock me-2"></i>{{ $summary['in_progress'] }} diproses</span>
                            <span class="info-badge"><i class="fas fa-check me-2"></i>{{ $summary['finalized'] }} final</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.graduations.create') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-plus"></i> Ajukan Yudisium
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([['label' => 'Total', 'value' => $summary['total'], 'icon' => 'fa-file-lines', 'color' => '#3b82f6'], ['label' => 'Diproses', 'value' => $summary['in_progress'], 'icon' => 'fa-clock', 'color' => '#f59e0b'], ['label' => 'Approved', 'value' => $summary['approved'], 'icon' => 'fa-check', 'color' => '#8b5cf6'], ['label' => 'Final', 'value' => $summary['finalized'], 'icon' => 'fa-user-graduate', 'color' => '#10b981']] as $card)
            <div class="col-md-3">
                <div class="card service-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon" style="background: {{ $card['color'] }}22; color: {{ $card['color'] }};">
                            <i class="fas {{ $card['icon'] }}"></i>
                        </span>
                        <div>
                            <div class="text-muted small">{{ $card['label'] }}</div>
                            <div class="h3 mb-0 fw-bold">{{ $card['value'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card service-card">
        <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
        <div class="">

            <h3 class="card-title mb-0">Riwayat Yudisium</h3>
                <small class="text-muted">Semua pengajuan yudisium kamu.</small>
        </div>    
        </div>
        <div class="card-body p-4">
            @if (! $hasProfile)
                <div class="text-center text-muted py-5">Student profile belum tersedia.</div>
            @elseif ($applications->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="fas fa-user-graduate fa-3x mb-3 opacity-50"></i>
                    <div class="h5">Belum ada pengajuan yudisium.</div>
                    <a href="{{ route('student.student-services.graduations.create') }}" class="btn btn-primary mt-2">Ajukan Yudisium Pertama</a>
                </div>
            @else
                <div class="d-grid gap-3">
                    @foreach ($applications as $application)
                        <div class="request-card">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div class="d-flex gap-3">
                                    <div class="metric-icon" style="width: 48px; height: 48px; background: #ede9fe; color: #7c3aed;">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <span class="badge {{ $this->statusClass($application->status) }} px-3 py-2">{{ $this->statusLabel($application->status) }}</span>
                                            <span class="badge bg-secondary-lt text-secondary px-3 py-2">{{ $application->graduation_period ?: '-' }}</span>
                                        </div>
                                        <h3 class="h4 mb-1" style="font-weight: 800; color: #111827;">{{ $application->application_number }}</h3>
                                        <div class="small mt-2 text-secondary">{{ $application->thesis_title ?: str($application->reason)->limit(150) }}</div>
                                    </div>
                                </div>
                                <a href="{{ route('student.student-services.graduations.show', ['id' => $application->id]) }}" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <i class="fas fa-eye"></i> Detail
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
