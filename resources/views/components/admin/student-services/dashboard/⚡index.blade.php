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
        .queue-item {
            display: block;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: .9rem;
            text-decoration: none;
            color: inherit;
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            transition: all .2s ease;
        }
        .queue-item:hover {
            border-color: #a5b4fc;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(99, 102, 241, .14);
        }
    </style>
@endpush

<div class="w-full" style="width: 100% !important" wire:poll.10s>
    <x-alert />
    <x-admin.student-services.header
        title="Dashboard Operasional Layanan"
        description="Pantau antrian surat, cuti, pindah, yudisium, dan pengaduan dari satu layar kerja operator kampus secara terpadu."
        icon="hands-helping"
    >
        <span class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 px-3 py-2 rounded-pill shadow-sm d-inline-flex align-items-center gap-1">
            <i class="fa fa-rotate me-1"></i> Auto Refresh 10s
        </span>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-envelope-open-text fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Surat Diproses</div>
                        <div class="fw-bold">{{ $this->stats()['pending_letters'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-minus fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Cuti Diproses</div>
                        <div class="fw-bold">{{ $this->stats()['pending_leaves'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-right-left fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Pindah Diproses</div>
                        <div class="fw-bold">{{ $this->stats()['pending_transfers'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-graduate fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Yudisium Diproses</div>
                        <div class="fw-bold">{{ $this->stats()['pending_graduations'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-headset fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Pengaduan Open</div>
                        <div class="fw-bold">{{ $this->stats()['open_complaints'] }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Beban Kerja Operator</h4>
                <div class="text-muted small">Statistik seluruh antrian layanan yang membutuhkan penanganan/review saat ini.</div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                @foreach ([
                    ['label' => 'Surat Diproses', 'value' => $this->stats()['pending_letters'], 'icon' => 'fa-envelope-open-text', 'color' => 'primary'],
                    ['label' => 'Cuti Diproses', 'value' => $this->stats()['pending_leaves'], 'icon' => 'fa-calendar-minus', 'color' => 'warning'],
                    ['label' => 'Pindah Diproses', 'value' => $this->stats()['pending_transfers'], 'icon' => 'fa-right-left', 'color' => 'info'],
                    ['label' => 'Yudisium Diproses', 'value' => $this->stats()['pending_graduations'], 'icon' => 'fa-user-graduate', 'color' => 'success'],
                    ['label' => 'Pengaduan Open', 'value' => $this->stats()['open_complaints'], 'icon' => 'fa-headset', 'color' => 'primary'],
                    ['label' => 'Lewat Batas SLA', 'value' => $this->stats()['overdue_complaints'], 'icon' => 'fa-triangle-exclamation', 'color' => 'danger'],
                ] as $card)
                    <div class="col-12 col-sm-6 col-xl-2">
                        <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                            <div class="text-muted small mb-1">{{ $card['label'] }}</div>
                            <div class="d-flex align-items-end justify-content-between">
                                <div class="fs-2 fw-bold text-dark lh-1">{{ $card['value'] }}</div>
                                <i class="fa {{ $card['icon'] }} fs-4 text-{{ $card['color'] }} opacity-75"></i>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-4">
        @php($queues = $this->queues())
        @foreach ([
            ['title' => 'Antrian Pengajuan Surat', 'items' => $queues['letters'], 'route' => 'admin.student-services.letter-requests.show', 'number' => 'request_number', 'subtitle' => fn($item) => $item->letterType?->name ?? '-'],
            ['title' => 'Antrian Pengajuan Cuti', 'items' => $queues['leaves'], 'route' => 'admin.student-services.leave-applications.show', 'number' => 'application_number', 'subtitle' => fn($item) => $item->academicYear?->name ?? 'Tahun akademik belum diisi'],
            ['title' => 'Antrian Pengajuan Pindah', 'items' => $queues['transfers'], 'route' => 'admin.student-services.transfer-requests.show', 'number' => 'request_number', 'subtitle' => fn($item) => ($item->fromStudyProgram?->name ?? '-').' -> '.($item->toStudyProgram?->name ?? '-')],
            ['title' => 'Antrian Pengajuan Yudisium', 'items' => $queues['graduations'], 'route' => 'admin.student-services.graduation-applications.show', 'number' => 'application_number', 'subtitle' => fn($item) => $item->graduationBatch?->name ?? $item->graduation_period],
        ] as $queue)
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
                        <h4 class="card-title fw-bold mb-0 text-dark">{{ $queue['title'] }}</h4>
                        <span class="badge bg-secondary-lt text-secondary">{{ $queue['items']->count() }} terbaru</span>
                    </div>
                    <div class="card-body p-3 p-md-4 d-grid gap-2">
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
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title fw-bold mb-0 text-dark">Pengaduan Prioritas SLA</h4>
                        <small class="text-muted">Urutan paling atas adalah tiket yang mendekati atau melewati batas waktu SLA.</small>
                    </div>
                    <a href="{{ route('admin.student-services.complaints.index') }}" class="btn btn-primary btn-sm rounded-pill px-3">Lihat Semua</a>
                </div>
                <div class="card-body p-3 p-md-4">
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
