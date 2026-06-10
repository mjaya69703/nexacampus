<?php

use App\Models\StudentService\StudentTransferRequest;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public StudentTransferRequest $request;

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 404);

        $this->request = StudentTransferRequest::with([
            'fromStudyProgram',
            'toStudyProgram',
            'histories.changedBy',
            'transferFeeInvoice',
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
            'pages' => 'Transfer Request Detail',
        ]);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'applied' => 'bg-green-lt text-green',
            'approved' => 'bg-blue-lt text-blue',
            'approved_pending_payment', 'revision_requested', 'in_approval' => 'bg-yellow-lt text-yellow',
            'under_review' => 'bg-indigo-lt text-indigo',
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
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function historyNotes($history): string
    {
        if ($history->to_status === 'in_approval' && $this->request->status === 'in_approval') {
            return $this->request->approvalRequest?->waitingMessage() ?? ($history->notes ?: '-');
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
                        <i class="fas fa-right-left"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Detail Transfer</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">{{ $request->request_number }}</h1>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="info-badge"><i class="fas fa-arrow-right me-2"></i>{{ $request->transfer_type === 'class_type' ? ucfirst($request->to_class_type ?? '-') : ($request->toStudyProgram?->name ?? '-') }}</span>
                            <span class="info-badge"><i class="fas fa-circle-info me-2"></i>{{ $this->statusLabel($request->status) }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.transfers') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
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
                        <div class="mt-1"><span class="badge {{ $this->statusClass($request->status) }}">{{ $this->statusLabel($request->status) }}</span></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">{{ $request->transfer_type === 'class_type' ? 'Kelas Saat Ini' : 'From Program' }}</small>
                        <div class="fw-bold mt-1">{{ $request->transfer_type === 'class_type' ? ucfirst($request->from_class_type ?? '-') : ($request->fromStudyProgram?->name ?? '-') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">{{ $request->transfer_type === 'class_type' ? 'Kelas Tujuan' : 'To Program' }}</small>
                        <div class="fw-bold mt-1">{{ $request->transfer_type === 'class_type' ? ucfirst($request->to_class_type ?? '-') : ($request->toStudyProgram?->name ?? '-') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">Recommended Semester</small>
                        <div class="fw-bold mt-1">{{ $request->recommended_semester ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-12">
                    <small class="text-muted">Alasan Transfer</small>
                    <div class="h6 mb-0">{{ $request->reason }}</div>
                </div>
                @foreach ([['label' => 'Catatan Admin', 'value' => $request->admin_notes], ['label' => 'Evaluasi Akademik', 'value' => $request->academic_evaluation_notes], ['label' => 'Catatan Mapping SKS', 'value' => $request->credit_mapping_notes], ['label' => 'Catatan Finance', 'value' => $request->finance_notes]] as $note)
                    @if ($note['value'])
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <strong>{{ $note['label'] }}:</strong> {{ $note['value'] }}
                            </div>
                        </div>
                    @endif
                @endforeach
                @if ($request->status === 'revision_requested')
                    <div class="col-12">
                        <a href="{{ route('student.student-services.transfers.edit', ['id' => $request->id]) }}" class="action-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: white;">
                            <i class="fas fa-pen-to-square me-1"></i> Perbaiki Pengajuan
                        </a>
                    </div>
                @endif
                @if ($request->transferFeeInvoice)
                    <div class="col-12">
                        <div class="alert alert-warning mb-0">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <strong>Biaya transfer perlu dibayar:</strong>
                                    Rp {{ number_format((float) $request->transfer_fee_amount, 0, ',', '.') }}
                                    <span class="ms-2">Invoice {{ $request->transferFeeInvoice->invoice_number }} ({{ str($request->transferFeeInvoice->status)->replace('_', ' ')->title() }})</span>
                                </div>
                                <a href="{{ route('student.financial.invoices.show', ['id' => $request->transferFeeInvoice->id]) }}" class="btn btn-sm btn-warning">
                                    Lihat Tagihan
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
                @if ($request->attachment_path)
                    <div class="col-12">
                        <a href="{{ $this->fileUrl($request->attachment_path) }}" target="_blank" class="action-btn" style="background: #dbeafe; color: #1d4ed8;">
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
            @foreach ($request->histories as $history)
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
