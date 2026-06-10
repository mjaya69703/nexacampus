<?php

use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\ServiceLetterRequest;
use App\Models\StudentService\StudentComplaint;
use App\Models\StudentService\StudentLeaveApplication;
use App\Models\StudentService\StudentTransferRequest;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Layanan Mahasiswa', 'pages' => 'Dashboard Layanan']);
    }

    public function stats(): array
    {
        return [
            'pending_letters' => ServiceLetterRequest::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved'])->count(),
            'pending_leaves' => StudentLeaveApplication::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved_pending_payment', 'approved'])->count(),
            'pending_transfers' => StudentTransferRequest::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved_pending_payment', 'approved'])->count(),
            'pending_graduations' => GraduationApplication::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved'])->count(),
            'open_complaints' => StudentComplaint::whereNotIn('status', ['resolved', 'closed', 'rejected'])->count(),
            'overdue_complaints' => StudentComplaint::whereNotIn('status', ['resolved', 'closed', 'rejected'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];
    }

    public function queues(): array
    {
        return [
            'letters' => ServiceLetterRequest::with(['letterType', 'studentProfile.user'])
                ->whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved'])
                ->latest()
                ->limit(5)
                ->get(),
            'leaves' => StudentLeaveApplication::with(['studentProfile.user', 'academicYear'])
                ->whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved_pending_payment', 'approved'])
                ->latest()
                ->limit(5)
                ->get(),
            'transfers' => StudentTransferRequest::with(['studentProfile.user', 'fromStudyProgram', 'toStudyProgram'])
                ->whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved_pending_payment', 'approved'])
                ->latest()
                ->limit(5)
                ->get(),
            'graduations' => GraduationApplication::with(['studentProfile.user', 'graduationBatch'])
                ->whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved'])
                ->latest()
                ->limit(5)
                ->get(),
            'complaints' => StudentComplaint::with(['studentProfile.user', 'category', 'assignedWorkUnit'])
                ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
                ->orderByRaw("CASE WHEN due_at IS NOT NULL AND due_at < NOW() THEN 0 ELSE 1 END")
                ->orderBy('due_at')
                ->limit(6)
                ->get(),
        ];
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Masuk',
            'in_approval' => 'Menunggu Approval',
            'under_review', 'in_review' => 'Direview',
            'revision_requested' => 'Perlu Perbaikan',
            'approved' => 'Approved',
            'approved_pending_payment' => 'Menunggu Bayar',
            'issued' => 'Terbit',
            'activated' => 'Aktif',
            'applied' => 'Diterapkan',
            'finalized' => 'Final',
            'waiting_student' => 'Menunggu Mahasiswa',
            'responded' => 'Dibalas',
            'reopened' => 'Dibuka Lagi',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'approved', 'issued', 'activated', 'applied', 'finalized' => 'bg-green-lt text-green',
            'revision_requested', 'waiting_student', 'approved_pending_payment', 'in_approval' => 'bg-yellow-lt text-yellow',
            'rejected', 'cancelled' => 'bg-red-lt text-red',
            'under_review', 'in_review', 'responded', 'reopened' => 'bg-blue-lt text-blue',
            default => 'bg-secondary-lt text-secondary',
        };
    }
};
?>

@push('styles')
    <style>
        .service-dashboard .hero {
            border: 0;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 0 14px 36px rgba(102, 126, 234, .22);
        }
        .service-dashboard .soft-card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, .08);
        }
        .service-dashboard .metric-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .service-dashboard .queue-item {
            display: block;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: .9rem;
            text-decoration: none;
            color: inherit;
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            transition: all .2s ease;
        }
        .service-dashboard .queue-item:hover {
            border-color: #a5b4fc;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(99, 102, 241, .14);
        }
    </style>
@endpush

<div class="service-dashboard" wire:poll.10s>
    <x-alert />

    <div class="card hero mb-4">
        <div class="card-body p-4 p-lg-5 d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div class="d-flex align-items-start gap-3">
                <div style="width:72px;height:72px;border-radius:18px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:2rem;">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <div>
                    <div style="opacity:.86;font-weight:700;text-transform:uppercase;font-size:.8rem;letter-spacing:.08em;">Layanan Mahasiswa</div>
                    <h1 class="h2 mb-2" style="font-weight:800;">Dashboard Operasional</h1>
                    <div style="opacity:.9;">Pantau antrian surat, cuti, pindah, yudisium, dan pengaduan dari satu layar kerja staff.</div>
                </div>
            </div>
            <span class="badge bg-white text-primary px-3 py-2"><i class="fas fa-rotate me-1"></i>Auto refresh 10 detik</span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Surat Diproses', 'value' => $this->stats()['pending_letters'], 'icon' => 'fa-envelope-open-text', 'color' => '#4f46e5'],
            ['label' => 'Cuti Diproses', 'value' => $this->stats()['pending_leaves'], 'icon' => 'fa-calendar-minus', 'color' => '#f59e0b'],
            ['label' => 'Pindah Diproses', 'value' => $this->stats()['pending_transfers'], 'icon' => 'fa-right-left', 'color' => '#06b6d4'],
            ['label' => 'Yudisium Diproses', 'value' => $this->stats()['pending_graduations'], 'icon' => 'fa-user-graduate', 'color' => '#8b5cf6'],
            ['label' => 'Pengaduan Open', 'value' => $this->stats()['open_complaints'], 'icon' => 'fa-headset', 'color' => '#10b981'],
            ['label' => 'Lewat SLA', 'value' => $this->stats()['overdue_complaints'], 'icon' => 'fa-triangle-exclamation', 'color' => '#ef4444'],
        ] as $card)
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card soft-card h-100">
                    <div class="card-body">
                        <span class="metric-icon mb-3" style="background: {{ $card['color'] }}22; color: {{ $card['color'] }};"><i class="fas {{ $card['icon'] }}"></i></span>
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <div class="h2 mb-0 fw-bold">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        @php($queues = $this->queues())
        @foreach ([
            ['title' => 'Pengajuan Surat', 'items' => $queues['letters'], 'route' => 'admin.student-services.letter-requests.show', 'number' => 'request_number', 'subtitle' => fn($item) => $item->letterType?->name ?? '-'],
            ['title' => 'Pengajuan Cuti', 'items' => $queues['leaves'], 'route' => 'admin.student-services.leave-applications.show', 'number' => 'application_number', 'subtitle' => fn($item) => $item->academicYear?->name ?? 'Tahun akademik belum diisi'],
            ['title' => 'Pengajuan Pindah', 'items' => $queues['transfers'], 'route' => 'admin.student-services.transfer-requests.show', 'number' => 'request_number', 'subtitle' => fn($item) => ($item->fromStudyProgram?->name ?? '-').' -> '.($item->toStudyProgram?->name ?? '-')],
            ['title' => 'Pengajuan Yudisium', 'items' => $queues['graduations'], 'route' => 'admin.student-services.graduation-applications.show', 'number' => 'application_number', 'subtitle' => fn($item) => $item->graduationBatch?->name ?? $item->graduation_period],
        ] as $queue)
            <div class="col-xl-6">
                <div class="card soft-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">{{ $queue['title'] }}</h3>
                        <span class="badge bg-secondary-lt text-secondary">{{ $queue['items']->count() }} terbaru</span>
                    </div>
                    <div class="card-body d-grid gap-2">
                        @forelse ($queue['items'] as $item)
                            <a href="{{ route($queue['route'], ['id' => $item->id]) }}" class="queue-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-bold">{{ $item->{$queue['number']} }} - {{ $item->studentProfile?->user?->name ?? '-' }}</div>
                                        <div class="small text-muted">{{ $queue['subtitle']($item) }}</div>
                                    </div>
                                    <span class="badge {{ $this->statusClass($item->status) }} align-self-start">{{ $this->statusLabel($item->status) }}</span>
                                </div>
                            </a>
                        @empty
                            <div class="text-center text-muted py-4">Tidak ada antrian aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-12">
            <div class="card soft-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="card-title mb-0">Pengaduan Prioritas SLA</h3>
                        <small class="text-muted">Urutan paling atas adalah tiket yang sudah/melewati SLA.</small>
                    </div>
                    <a href="{{ route('admin.student-services.complaints.index') }}" class="btn btn-primary btn-sm">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @forelse ($queues['complaints'] as $complaint)
                            <div class="col-lg-4">
                                <a href="{{ route('admin.student-services.complaints.show', ['id' => $complaint->id]) }}" class="queue-item h-100">
                                    <div class="d-flex justify-content-between gap-3 mb-2">
                                        <span class="badge {{ $this->statusClass($complaint->status) }}">{{ $this->statusLabel($complaint->status) }}</span>
                                        <span class="small {{ $complaint->due_at && $complaint->due_at->isPast() ? 'text-danger fw-bold' : 'text-muted' }}">
                                            {{ $complaint->due_at?->diffForHumans() ?? '-' }}
                                        </span>
                                    </div>
                                    <div class="fw-bold">{{ $complaint->ticket_number }} - {{ $complaint->subject }}</div>
                                    <div class="small text-muted mt-1">{{ $complaint->studentProfile?->user?->name ?? '-' }} / {{ $complaint->assignedWorkUnit?->name ?? 'Belum diarahkan' }}</div>
                                </a>
                            </div>
                        @empty
                            <div class="col-12 text-center text-muted py-4">Tidak ada pengaduan aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
