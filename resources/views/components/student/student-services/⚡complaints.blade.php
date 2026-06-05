<?php

use App\Models\StudentService\StudentComplaint;
use Livewire\Component;

new class extends Component
{
    public $complaints;
    public bool $hasProfile = false;
    public array $summary = ['total' => 0, 'active' => 0, 'waiting' => 0, 'resolved' => 0];

    public function mount(): void
    {
        $profile = auth()->user()?->studentProfile;
        $this->complaints = collect();
        if (! $profile) {
            return;
        }
        $this->hasProfile = true;
        $this->complaints = StudentComplaint::with(['category', 'assignedWorkUnit'])
            ->where('student_profile_id', $profile->id)
            ->latest()
            ->get();

        $this->summary = [
            'total' => $this->complaints->count(),
            'active' => $this->complaints->whereNotIn('status', ['resolved', 'closed', 'rejected'])->count(),
            'waiting' => $this->complaints->where('status', 'waiting_student')->count(),
            'resolved' => $this->complaints->whereIn('status', ['resolved', 'closed'])->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Student Services', 'pages' => 'Pengaduan']);
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Masuk',
            'in_review' => 'Direview',
            'waiting_student' => 'Menunggu Kamu',
            'responded' => 'Dibalas',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup',
            'rejected' => 'Ditolak',
            'reopened' => 'Dibuka Lagi',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'resolved', 'closed' => 'bg-green-lt text-green',
            'waiting_student' => 'bg-yellow-lt text-yellow',
            'rejected' => 'bg-red-lt text-red',
            'responded' => 'bg-blue-lt text-blue',
            'in_review', 'reopened' => 'bg-indigo-lt text-indigo',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'Rendah',
            'high' => 'Tinggi',
            'urgent' => 'Urgent',
            default => 'Normal',
        };
    }
};
?>

@include('components.student.student-services.service-styles')

<div>
    <x-alert />
    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,.2); border-radius: 18px; display:flex; align-items:center; justify-content:center; font-size:2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <div style="font-size: .9rem; opacity: .88;">Layanan Mahasiswa</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Pengaduan Mahasiswa</h1>
                        <div style="opacity: .9;">Buat tiket, upload bukti, balas percakapan, dan pantau penyelesaian dari unit kampus.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="info-badge"><i class="fas fa-ticket me-2"></i>{{ $summary['total'] }} tiket</span>
                            <span class="info-badge"><i class="fas fa-clock me-2"></i>{{ $summary['active'] }} aktif</span>
                            <span class="info-badge"><i class="fas fa-user-clock me-2"></i>{{ $summary['waiting'] }} menunggu kamu</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.complaints.create') }}" class="action-btn" style="background: rgba(255,255,255,.94); color: #4f46e5;">
                    <i class="fas fa-plus"></i> Buat Pengaduan
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([['label' => 'Total Tiket', 'value' => $summary['total'], 'icon' => 'fa-ticket', 'color' => '#4f46e5'], ['label' => 'Sedang Diproses', 'value' => $summary['active'], 'icon' => 'fa-spinner', 'color' => '#3b82f6'], ['label' => 'Perlu Balasan', 'value' => $summary['waiting'], 'icon' => 'fa-user-clock', 'color' => '#f59e0b'], ['label' => 'Selesai', 'value' => $summary['resolved'], 'icon' => 'fa-circle-check', 'color' => '#10b981']] as $card)
            <div class="col-md-3">
                <div class="ticket-stat d-flex align-items-center gap-3">
                    <span class="metric-icon" style="background: {{ $card['color'] }}22; color: {{ $card['color'] }};"><i class="fas {{ $card['icon'] }}"></i></span>
                    <div>
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <div class="h3 mb-0 fw-bold">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card service-card">
        <div class="card-header service-section-header">
            <div class="d-flex align-items-center gap-3">
                <span class="metric-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-list-check"></i></span>
                <div>
                    <h3 class="card-title mb-0" style="font-weight:800;">Riwayat Pengaduan</h3>
                    <small class="text-muted">Klik detail untuk melihat thread dan balasan staff.</small>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            @if (! $hasProfile)
                <div class="text-center text-muted py-5">Student profile belum tersedia.</div>
            @elseif ($complaints->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="fas fa-headset fa-3x mb-3 opacity-50"></i>
                    <div class="h5">Belum ada pengaduan.</div>
                    <a href="{{ route('student.student-services.complaints.create') }}" class="btn btn-primary mt-2">Buat Pengaduan Pertama</a>
                </div>
            @else
                <div class="d-grid gap-3">
                    @foreach ($complaints as $complaint)
                        <div class="request-card">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div class="d-flex gap-3">
                                    <span class="metric-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-ticket"></i></span>
                                    <div>
                                    <div class="d-flex gap-2 flex-wrap mb-2">
                                        <span class="badge {{ $this->statusClass($complaint->status) }} px-3 py-2">{{ $this->statusLabel($complaint->status) }}</span>
                                        <span class="badge bg-secondary-lt text-secondary px-3 py-2">{{ $complaint->category?->name ?? '-' }}</span>
                                        <span class="badge bg-secondary-lt text-secondary px-3 py-2">{{ $this->priorityLabel($complaint->priority) }}</span>
                                    </div>
                                    <h3 class="h4 mb-1" style="font-weight: 800;">{{ $complaint->subject }}</h3>
                                    <div class="text-muted small">{{ $complaint->ticket_number }} - {{ $complaint->created_at->format('d M Y H:i') }}</div>
                                    </div>
                                </div>
                                <a href="{{ route('student.student-services.complaints.show', ['id' => $complaint->id]) }}" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
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
