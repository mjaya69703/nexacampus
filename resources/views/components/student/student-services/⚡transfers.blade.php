<?php

use App\Models\StudentService\StudentTransferRequest;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;

    public $requests;

    public array $summary = ['total' => 0, 'in_progress' => 0, 'approved' => 0, 'applied' => 0];

    public function mount(): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        $this->requests = collect();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->requests = StudentTransferRequest::query()
            ->with(['fromStudyProgram', 'toStudyProgram'])
            ->where('student_profile_id', $studentProfile->id)
            ->orderByDesc('created_at')
            ->get();

        $this->summary = [
            'total' => $this->requests->count(),
            'in_progress' => $this->requests->whereIn('status', ['submitted', 'under_review', 'revision_requested', 'approved_pending_payment'])->count(),
            'approved' => $this->requests->where('status', 'approved')->count(),
            'applied' => $this->requests->where('status', 'applied')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Transfer Requests',
        ]);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'applied' => 'bg-green-lt text-green',
            'approved' => 'bg-blue-lt text-blue',
            'approved_pending_payment', 'revision_requested' => 'bg-yellow-lt text-yellow',
            'under_review' => 'bg-indigo-lt text-indigo',
            'rejected', 'cancelled' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'approved_pending_payment' => 'Menunggu Pembayaran',
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
                        <i class="fas fa-right-left"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Student Services</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Pindah Program</h1>
                        <div style="opacity: 0.9; margin-bottom: 1rem;">Ajukan pindah program internal, pantau evaluasi, dan lihat status penerapannya.</div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="info-badge"><i class="fas fa-file-lines me-2"></i>{{ $summary['total'] }} pengajuan</span>
                            <span class="info-badge"><i class="fas fa-clock me-2"></i>{{ $summary['in_progress'] }} diproses</span>
                            <span class="info-badge"><i class="fas fa-check me-2"></i>{{ $summary['applied'] }} diterapkan</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.transfers.create') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-plus"></i> Ajukan Transfer
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([['label' => 'Total', 'value' => $summary['total'], 'icon' => 'fa-file-lines', 'color' => '#3b82f6'], ['label' => 'Diproses', 'value' => $summary['in_progress'], 'icon' => 'fa-clock', 'color' => '#f59e0b'], ['label' => 'Approved', 'value' => $summary['approved'], 'icon' => 'fa-check', 'color' => '#8b5cf6'], ['label' => 'Applied', 'value' => $summary['applied'], 'icon' => 'fa-user-check', 'color' => '#10b981']] as $card)
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
        <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">Riwayat Transfer</h3>
                <small class="text-muted">Semua pengajuan pindah program kamu.</small>
            </div>
        </div>
        <div class="card-body p-4">
            @if (! $hasProfile)
                <div class="text-center text-muted py-5">Student profile belum tersedia.</div>
            @elseif ($requests->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="fas fa-right-left fa-3x mb-3 opacity-50"></i>
                    <div class="h5">Belum ada pengajuan transfer.</div>
                    <a href="{{ route('student.student-services.transfers.create') }}" class="btn btn-primary mt-2">Ajukan Transfer Pertama</a>
                </div>
            @else
                <div class="d-grid gap-3">
                    @foreach ($requests as $request)
                        <div class="request-card">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div class="d-flex gap-3">
                                    <div class="metric-icon" style="width: 48px; height: 48px; background: #ede9fe; color: #7c3aed;">
                                        <i class="fas fa-right-left"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <span class="badge {{ $this->statusClass($request->status) }} px-3 py-2">{{ $this->statusLabel($request->status) }}</span>
                                            <span class="badge bg-secondary-lt text-secondary px-3 py-2">{{ str($request->transfer_type)->replace('_', ' ')->title() }}</span>
                                            <span class="badge bg-secondary-lt text-secondary px-3 py-2">
                                                @if ($request->transfer_type === 'class_type')
                                                    {{ ucfirst($request->from_class_type ?? '-') }} -> {{ ucfirst($request->to_class_type ?? '-') }}
                                                @else
                                                    {{ $request->fromStudyProgram?->name ?? '-' }} -> {{ $request->toStudyProgram?->name ?? '-' }}
                                                @endif
                                            </span>
                                        </div>
                                        <h3 class="h4 mb-1" style="font-weight: 800; color: #111827;">{{ $request->request_number }}</h3>
                                        <div class="small mt-2 text-secondary">{{ str($request->reason)->limit(150) }}</div>
                                    </div>
                                </div>
                                <a href="{{ route('student.student-services.transfers.show', ['id' => $request->id]) }}" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
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
