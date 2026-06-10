<?php

use App\Models\StudentService\StudentLeaveApplication;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public StudentLeaveApplication $application;

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 404);

        $this->application = StudentLeaveApplication::with([
            'academicYear',
            'histories.changedBy',
            'leaveFeeInvoice',
            'approvalRequest.steps.approverUser',
            'approvalRequest.steps.organizationalPosition',
            'approvalRequest.steps.workUnit',
        ])
            ->where('student_profile_id', $studentProfile->id)
            ->findOrFail($id);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Leave Application Detail',
        ]);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'returned', 'activated' => 'bg-green-lt text-green',
            'approved' => 'bg-blue-lt text-blue',
            'approved_pending_payment', 'in_approval' => 'bg-yellow-lt text-yellow',
            'under_review' => 'bg-indigo-lt text-indigo',
            'revision_requested' => 'bg-yellow-lt text-yellow',
            'rejected', 'cancelled' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'in_approval' => 'Menunggu Persetujuan',
            'approved_pending_payment' => 'Menunggu Pembayaran',
            'activated' => 'Cuti Aktif',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function historyNotes($history): string
    {
        if ($history->to_status === 'in_approval' && $this->application->status === 'in_approval') {
            return $this->application->approvalRequest?->waitingMessage() ?? ($history->notes ?: '-');
        }

        return $history->notes ?: '-';
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
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
                        <i class="fas fa-calendar-minus"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Detail Cuti Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">{{ $application->application_number }}</h1>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="info-badge"><i class="fas fa-calendar me-2"></i>{{ $application->academicYear?->name ?? '-' }}</span>
                            <span class="info-badge"><i class="fas fa-clock me-2"></i>{{ $application->duration_semesters }} semester</span>
                            <span class="info-badge"><i class="fas fa-circle-info me-2"></i>{{ $this->statusLabel($application->status) }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.leaves') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="card service-card mb-3">
        <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <h3 class="card-title mb-0">Informasi Pengajuan</h3>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">Status</small>
                        <div class="mt-1"><span class="badge {{ $this->statusClass($application->status) }}">{{ $this->statusLabel($application->status) }}</span></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">Semester</small>
                        <div class="fw-bold mt-1">{{ $application->semester ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">Duration</small>
                        <div class="fw-bold mt-1">{{ $application->duration_semesters }} semester</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">Reason</small>
                        <div class="fw-bold mt-1">{{ str($application->reason_category)->replace('_', ' ')->title() }}</div>
                    </div>
                </div>
                <div class="col-12">
                    <small class="text-muted">Alasan Cuti</small>
                    <div class="h6 mb-0">{{ $application->reason }}</div>
                </div>
                @if ($application->admin_notes)
                    <div class="col-12">
                        <div class="alert {{ $application->status === 'revision_requested' ? 'alert-warning' : 'alert-info' }} mb-0">
                            <strong>Catatan Admin:</strong> {{ $application->admin_notes }}
                        </div>
                    </div>
                @endif
                @if ($application->status === 'revision_requested')
                    <div class="col-12">
                        <a href="{{ route('student.student-services.leaves.edit', ['id' => $application->id]) }}" class="action-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: white;">
                            <i class="fas fa-pen-to-square me-1"></i> Perbaiki Pengajuan
                        </a>
                    </div>
                @endif
                @if ($application->leaveFeeInvoice)
                    <div class="col-12">
                        <div class="alert alert-warning mb-0">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <strong>Biaya cuti perlu dibayar:</strong>
                                    Rp {{ number_format((float) $application->leave_fee_amount, 0, ',', '.') }}
                                    <span class="ms-2">Invoice {{ $application->leaveFeeInvoice->invoice_number }} ({{ str($application->leaveFeeInvoice->status)->replace('_', ' ')->title() }})</span>
                                </div>
                                <a href="{{ route('student.financial.invoices.show', ['id' => $application->leaveFeeInvoice->id]) }}" class="btn btn-sm btn-warning">
                                    Lihat Tagihan
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
                @if ($application->attachment_path)
                    <div class="col-12">
                        <a href="{{ $this->fileUrl($application->attachment_path) }}" target="_blank" class="action-btn" style="background: #dbeafe; color: #1d4ed8;">
                            <i class="fas fa-paperclip me-1"></i> Lihat Lampiran
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card service-card">
        <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <h3 class="card-title mb-0">Timeline</h3>
        </div>
        <div class="list-group list-group-flush">
            @foreach ($application->histories as $history)
                <div class="list-group-item">
                    <div class="d-flex gap-3">
                        <span class="timeline-dot"><i class="fas fa-check"></i></span>
                        <div class="flex-fill">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <strong>{{ $this->statusLabel($history->to_status) }}</strong>
                                <span class="text-muted">{{ $history->created_at?->format('d M Y H:i') }}</span>
                            </div>
                            <div class="small text-muted">{{ $this->historyNotes($history) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
