<?php

use App\Models\StudentService\ServiceLetterRequest;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public ServiceLetterRequest $request;

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 404);

        $this->request = ServiceLetterRequest::with([
            'letterType',
            'histories.changedBy',
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
            'pages' => 'Letter Request Detail',
        ]);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'issued' => 'bg-green-lt text-green',
            'approved' => 'bg-blue-lt text-blue',
            'under_review' => 'bg-indigo-lt text-indigo',
            'revision_requested', 'in_approval' => 'bg-yellow-lt text-yellow',
            'rejected', 'cancelled' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'in_approval' => 'Menunggu Persetujuan',
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

@push('styles')
    <style>
        .service-card {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            background: white;
        }
        .hero-gradient {
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            overflow: hidden;
            position: relative;
        }
        .hero-gradient::before {
            content: '';
            position: absolute;
            inset: -60% -30% auto auto;
            width: 420px;
            height: 420px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
        }
        .info-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.42rem 0.75rem;
            background: rgba(255,255,255,0.18);
            border-radius: 8px;
            color: white;
            font-size: 0.84rem;
            backdrop-filter: blur(10px);
        }
        .detail-tile {
            border-radius: 14px;
            background: #f8fafc;
            padding: 1rem;
            height: 100%;
        }
        .timeline-dot {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ede9fe;
            color: #7c3aed;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border-radius: 10px;
            padding: .65rem 1.05rem;
            border: 0;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position: relative;">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-file-lines"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Detail Request</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">{{ $request->letterType?->name }}</h1>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="info-badge"><i class="fas fa-hashtag me-2"></i>{{ $request->request_number }}</span>
                            <span class="info-badge"><i class="fas fa-calendar me-2"></i>{{ $request->created_at?->format('d M Y H:i') }}</span>
                            <span class="info-badge"><i class="fas fa-circle-info me-2"></i>{{ $this->statusLabel($request->status) }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.letters') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
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
                <div class="col-md-4">
                    <div class="detail-tile">
                        <small class="text-muted">Status</small>
                        <div class="mt-1"><span class="badge {{ $this->statusClass($request->status) }}">{{ $this->statusLabel($request->status) }}</span></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="detail-tile">
                        <small class="text-muted">Submitted</small>
                        <div class="fw-bold mt-1">{{ $request->created_at?->format('d M Y H:i') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="detail-tile">
                        <small class="text-muted">Issued</small>
                        <div class="fw-bold mt-1">{{ $request->issued_at?->format('d M Y H:i') ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-12">
                    <small class="text-muted">Keperluan</small>
                    <div class="h6 mb-0">{{ $request->purpose }}</div>
                </div>
                @if ($request->admin_notes)
                    <div class="col-12">
                        <div class="alert {{ $request->status === 'revision_requested' ? 'alert-warning' : 'alert-info' }} mb-0">
                            <strong>Catatan Admin:</strong> {{ $request->admin_notes }}
                        </div>
                    </div>
                @endif
                @if ($request->status === 'revision_requested')
                    <div class="col-12">
                        <a href="{{ route('student.student-services.letters.edit', ['id' => $request->id]) }}" class="action-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: white;">
                            <i class="fas fa-pen-to-square me-1"></i> Perbaiki Pengajuan
                        </a>
                    </div>
                @endif
                @if ($request->isDownloadable())
                    <div class="col-12">
                        <a href="{{ route('student.student-services.letters.download', ['request' => $request->id]) }}" target="_blank" class="action-btn" style="background: linear-gradient(135deg, #10b981 0%, #22c55e 100%); color: white;">
                            <i class="fas fa-download me-1"></i> Download Surat
                        </a>
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
