<?php

use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\GraduationDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public GraduationApplication $application;

    public array $checklistUsers = [];

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 404);

        $this->application = GraduationApplication::with(['histories.changedBy', 'academicPeriod', 'graduationBatch', 'documents.requirement', 'documents.verifiedBy'])
            ->where('student_profile_id', $studentProfile->id)
            ->findOrFail($id);
        $this->loadChecklistUsers();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Graduation Application Detail',
        ]);
    }

    public function refreshApplication(): void
    {
        $this->application->refresh()->load(['histories.changedBy', 'academicPeriod', 'graduationBatch', 'documents.requirement', 'documents.verifiedBy']);
        $this->loadChecklistUsers();
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

    public function documentStatusClass(string $status): string
    {
        return match ($status) {
            'verified' => 'bg-green-lt text-green',
            'rejected' => 'bg-red-lt text-red',
            default => 'bg-yellow-lt text-yellow',
        };
    }

    public function documentStatusLabel(string $status): string
    {
        return match ($status) {
            'verified' => 'Terverifikasi',
            'rejected' => 'Perlu diganti',
            default => 'Menunggu Verifikasi',
        };
    }

    public function checklistLabels(): array
    {
        return [
            'transcript_checked' => 'Transkrip dicek',
            'final_project_checked' => 'Skripsi/TA dicek',
            'library_clearance' => 'Bebas pustaka',
            'lab_clearance' => 'Bebas lab',
            'document_complete' => 'Dokumen lengkap',
        ];
    }

    public function checklistCompletedCount(): int
    {
        return collect($this->checklistLabels())
            ->filter(fn ($label, $key) => $this->checklistItemChecked($key))
            ->count();
    }

    public function checklistItemChecked(string $key): bool
    {
        $item = ($this->application->admin_checklist ?? [])[$key] ?? [];

        return is_array($item) ? (bool) ($item['checked'] ?? false) : (bool) $item;
    }

    public function checklistItemCheckedAt(string $key): ?string
    {
        $item = ($this->application->admin_checklist ?? [])[$key] ?? [];

        return is_array($item) && ! empty($item['checked_at'])
            ? \Illuminate\Support\Carbon::parse($item['checked_at'])->format('d M Y H:i')
            : null;
    }

    public function checklistItemCheckedBy(string $key): ?string
    {
        $item = ($this->application->admin_checklist ?? [])[$key] ?? [];
        $userId = is_array($item) ? ($item['checked_by'] ?? null) : null;

        return $userId ? ($this->checklistUsers[$userId] ?? 'Admin') : null;
    }

    public function checklistItemNotes(string $key): ?string
    {
        $item = ($this->application->admin_checklist ?? [])[$key] ?? [];

        return is_array($item) ? (($item['notes'] ?? null) ?: null) : null;
    }

    public function studentNextStep(): array
    {
        if ($this->application->status === 'submitted') {
            return [
                'tone' => 'blue',
                'icon' => 'fa-clock',
                'title' => 'Pengajuan sudah masuk',
                'body' => 'Admin akademik akan mulai mengecek eligibility, dokumen, dan checklist yudisium kamu.',
            ];
        }

        if ($this->application->status === 'under_review') {
            return [
                'tone' => 'indigo',
                'icon' => 'fa-list-check',
                'title' => 'Sedang direview',
                'body' => 'Pantau progress checklist di bawah. Jika admin butuh perbaikan, status akan berubah menjadi Perlu Perbaikan.',
            ];
        }

        if ($this->application->status === 'revision_requested') {
            return [
                'tone' => 'yellow',
                'icon' => 'fa-pen-to-square',
                'title' => 'Perlu perbaikan dari kamu',
                'body' => 'Baca catatan admin, perbaiki data atau lampiran, lalu kirim ulang pengajuan.',
            ];
        }

        if ($this->application->status === 'approved') {
            return [
                'tone' => 'blue',
                'icon' => 'fa-thumbs-up',
                'title' => 'Pengajuan disetujui',
                'body' => 'Pengajuan sudah approved dan menunggu finalisasi yudisium oleh admin akademik.',
            ];
        }

        if ($this->application->status === 'finalized') {
            return [
                'tone' => 'green',
                'icon' => 'fa-user-graduate',
                'title' => 'Yudisium final',
                'body' => 'Status akademik kamu sudah difinalisasi sebagai Lulus.',
            ];
        }

        if ($this->application->status === 'rejected') {
            return [
                'tone' => 'red',
                'icon' => 'fa-circle-xmark',
                'title' => 'Pengajuan ditolak',
                'body' => 'Lihat catatan admin untuk mengetahui alasan penolakan.',
            ];
        }

        return [
            'tone' => 'secondary',
            'icon' => 'fa-circle-info',
            'title' => 'Status pengajuan',
            'body' => 'Pantau detail dan timeline pengajuan yudisium kamu di halaman ini.',
        ];
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function documentPreviewUrl(GraduationDocument $document): string
    {
        return route('student.student-services.graduation-documents.preview', ['document' => $document->id]);
    }

    private function loadChecklistUsers(): void
    {
        $ids = collect($this->application->admin_checklist ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['checked_by'] ?? null) : null)
            ->filter()
            ->unique()
            ->values();

        $this->checklistUsers = $ids->isEmpty()
            ? []
            : User::whereIn('id', $ids)
                ->get(['id', 'first_name', 'last_name'])
                ->mapWithKeys(fn (User $user) => [$user->id => trim($user->first_name.' '.$user->last_name)])
                ->toArray();
    }
};
?>

@include('components.student.student-services.service-styles')

<div wire:poll.15s="refreshApplication">
    <x-alert />

    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position: relative;">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Detail Yudisium</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">{{ $application->application_number }}</h1>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="info-badge"><i class="fas fa-calendar me-2"></i>{{ $application->graduation_period ?: '-' }}</span>
                            <span class="info-badge"><i class="fas fa-user-graduate me-2"></i>Lulus: {{ $application->graduation_date?->format('d M Y') ?? 'Belum final' }}</span>
                            <span class="info-badge"><i class="fas fa-circle-info me-2"></i>{{ $this->statusLabel($application->status) }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.graduations') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    @php($nextStep = $this->studentNextStep())
    <div class="alert bg-{{ $nextStep['tone'] }}-lt text-{{ $nextStep['tone'] }} border-0 mb-3">
        <div class="d-flex gap-3 align-items-start">
            <i class="fas {{ $nextStep['icon'] }} mt-1"></i>
            <div>
                <div class="fw-bold">{{ $nextStep['title'] }}</div>
                <div>{{ $nextStep['body'] }}</div>
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
                        <small class="text-muted">Batch Yudisium</small>
                        <div class="fw-bold mt-1">{{ $application->graduationBatch?->name ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">Tanggal Yudisium/Lulus</small>
                        <div class="fw-bold mt-1">{{ $application->graduation_date?->format('d M Y') ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">Tanggal Lulus Tercatat</small>
                        <div class="fw-bold mt-1">{{ $application->graduation_date?->format('d M Y') ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-tile">
                        <small class="text-muted">Submitted</small>
                        <div class="fw-bold mt-1">{{ $application->created_at?->format('d M Y') }}</div>
                    </div>
                </div>
                <div class="col-12">
                    <small class="text-muted">Judul Skripsi / Tugas Akhir</small>
                    <div class="h6 mb-0">{{ $application->thesis_title ?: '-' }}</div>
                </div>
                @foreach ([['label' => 'Catatan Mahasiswa', 'value' => $application->student_notes], ['label' => 'Catatan Admin', 'value' => $application->admin_notes]] as $note)
                    @if ($note['value'])
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <strong>{{ $note['label'] }}:</strong> {{ $note['value'] }}
                            </div>
                        </div>
                    @endif
                @endforeach
                @if ($application->status === 'revision_requested')
                    <div class="col-12">
                        <a href="{{ route('student.student-services.graduations.edit', ['id' => $application->id]) }}" class="action-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: white;">
                            <i class="fas fa-pen-to-square me-1"></i> Perbaiki Pengajuan
                        </a>
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

    <div class="card service-card mb-3">
        <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">Dokumen Yudisium</h3>
                <small class="text-muted d-block mt-1">Status verifikasi dokumen yang kamu upload.</small>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                @forelse ($application->documents as $document)
                    <div class="col-md-6">
                        <div class="detail-tile h-100">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div>
                                    <div class="fw-bold">{{ $document->requirement?->label ?? str($document->document_type)->replace('_', ' ')->title() }}</div>
                                    <div class="small text-muted">{{ $document->file_name }}</div>
                                </div>
                                <span class="badge {{ $this->documentStatusClass($document->verification_status) }}">
                                    {{ $this->documentStatusLabel($document->verification_status) }}
                                </span>
                            </div>
                            @if ($document->verified_at)
                                <div class="small text-muted mb-2">
                                    Dicek {{ $document->verified_at?->format('d M Y H:i') }}.
                                </div>
                            @endif
                            @if ($document->verification_notes)
                                <div class="small mb-2 {{ $document->verification_status === 'rejected' ? 'text-danger' : 'text-muted' }}">
                                    {{ $document->verification_notes }}
                                </div>
                            @endif
                            <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank" class="action-btn" style="background: #dbeafe; color: #1d4ed8;">
                                <i class="fas fa-eye me-1"></i> Preview
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="detail-tile text-muted">
                            Belum ada dokumen yudisium yang terupload.
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card service-card mb-3">
        <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">Progress Review Akademik</h3>
                <small class="text-muted d-block mt-1">Checklist ini diupdate otomatis oleh admin saat pengajuan direview.</small>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-bold">{{ $this->checklistCompletedCount() }}/{{ count($this->checklistLabels()) }} checklist selesai</span>
                <span class="badge {{ $this->checklistCompletedCount() === count($this->checklistLabels()) ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">
                    {{ $this->checklistCompletedCount() === count($this->checklistLabels()) ? 'Lengkap' : 'Dalam Review' }}
                </span>
            </div>
            <div class="progress mb-3" style="height: 8px;">
                <div class="progress-bar bg-success" style="width: {{ count($this->checklistLabels()) ? ($this->checklistCompletedCount() / count($this->checklistLabels()) * 100) : 0 }}%;"></div>
            </div>
            <div class="row g-2">
                @foreach ($this->checklistLabels() as $key => $label)
                    @php($checked = $this->checklistItemChecked($key))
                    <div class="col-md-6">
                        <div class="detail-tile">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas {{ $checked ? 'fa-check-circle text-success' : 'fa-clock text-muted' }}"></i>
                                <span class="fw-bold">{{ $label }}</span>
                            </div>
                            @if ($checked)
                                <div class="small text-muted mt-1">
                                    Dicek oleh {{ $this->checklistItemCheckedBy($key) ?? 'Admin' }}{{ $this->checklistItemCheckedAt($key) ? ' pada '.$this->checklistItemCheckedAt($key) : '' }}.
                                </div>
                            @endif
                            @if ($this->checklistItemNotes($key))
                                <div class="small mt-2">{{ $this->checklistItemNotes($key) }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
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
                            <div class="small text-muted">{{ $history->notes ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
