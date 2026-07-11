<?php

use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\GraduationDocument;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\StudentService\GraduationApplicationService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public GraduationApplication $application;

    public ?string $adminNotes = null;

    public ?string $graduationDate = null;

    public array $checklist = [];

    public array $checklistUsers = [];

    public array $documentNotes = [];

    public function mount($id): void
    {
        $this->application = GraduationApplication::with([
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'finalizedBy',
            'academicPeriod',
            'graduationBatch',
            'approvalRequest.steps.actedBy',
            'documents.requirement',
            'documents.verifiedBy',
        ])->findOrFail($id);

        $this->adminNotes = $this->application->admin_notes;
        $this->graduationDate = $this->application->graduation_date?->format('Y-m-d') ?? $this->application->graduationBatch?->yudisium_date?->format('Y-m-d');
        $this->checklist = $this->application->admin_checklist ?: app(GraduationApplicationService::class)->defaultChecklist();
        $this->loadDocumentNotes();
        $this->loadChecklistUsers();
    }

    public function updatedChecklist($value, $key): void
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        try {
            $this->application = app(GraduationApplicationService::class)
                ->saveChecklist($this->application, $this->checklist, $this->adminNotes, auth()->id());
            $this->checklist = $this->application->admin_checklist ?: app(GraduationApplicationService::class)->defaultChecklist();
            $this->application->load([
                'studentProfile.user',
                'studentProfile.studyProgram.faculty',
                'histories.changedBy',
                'reviewedBy',
                'approvedBy',
                'finalizedBy',
                'academicPeriod',
                'graduationBatch',
                'approvalRequest.steps.actedBy',
                'documents.requirement',
                'documents.verifiedBy',
            ]);
            $this->loadDocumentNotes();
            $this->loadChecklistUsers();
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
            $this->reload();
        }
    }

    public function markUnderReview(GraduationApplicationService $service): void
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);
        $this->application = $service->setStatus($this->application, 'under_review', $this->adminNotes, auth()->id());
        session()->flash('success', 'Pengajuan yudisium ditandai under review.');
        $this->reload();
    }

    public function verifyDocument(int $documentId, string $status, GraduationApplicationService $service): void
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        try {
            $document = $this->application->documents->firstWhere('id', $documentId);
            abort_unless($document, 404);

            $service->verifyDocument($document, $status, $this->documentNotes[$documentId] ?? null, auth()->id());
            session()->flash('success', 'Status dokumen yudisium berhasil diperbarui.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function approve(GraduationApplicationService $service): void
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        try {
            $this->application = $service->approve($this->application, $this->checklist, $this->adminNotes, auth()->id());
            session()->flash('success', 'Pengajuan yudisium berhasil diapprove.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function requestCorrection(GraduationApplicationService $service): void
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);
        $this->application = $service->setStatus($this->application, 'revision_requested', $this->adminNotes, auth()->id());
        session()->flash('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
        $this->reload();
    }

    public function reject(GraduationApplicationService $service): void
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);
        try {
            $this->application = $service->reject($this->application, $this->adminNotes, auth()->id());
            session()->flash('success', 'Pengajuan yudisium ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
        $this->reload();
    }

    public function finalize(GraduationApplicationService $service): void
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        try {
            $this->application = $service->finalize($this->application, $this->graduationDate, $this->adminNotes, auth()->id());
            session()->flash('success', 'Yudisium berhasil difinalisasi. Status mahasiswa menjadi Lulus.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Detail Pengajuan Yudisium',
        ]);
    }

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'finalized' => 'bg-success',
            'approved' => 'bg-info',
            'revision_requested', 'in_approval' => 'bg-warning text-dark',
            'under_review' => 'bg-primary',
            'rejected', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'in_approval' => 'Menunggu Approval',
            'finalized' => 'Final',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function documentStatusBadge(string $status): string
    {
        return match ($status) {
            'verified' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-warning text-dark',
        };
    }

    public function documentStatusLabel(string $status): string
    {
        return match ($status) {
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            default => 'Pending',
        };
    }

    public function checklistLabels(): array
    {
        return [
            'transcript_checked' => 'Transkrip sudah dicek',
            'final_project_checked' => 'Skripsi/TA sudah dicek',
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

    public function checklistIsComplete(): bool
    {
        return $this->checklistCompletedCount() === count($this->checklistLabels());
    }

    public function checklistItemChecked(string $key): bool
    {
        $item = $this->checklist[$key] ?? [];

        return is_array($item) ? (bool) ($item['checked'] ?? false) : (bool) $item;
    }

    public function checklistItemNotes(string $key): ?string
    {
        $item = $this->checklist[$key] ?? [];

        return is_array($item) ? (($item['notes'] ?? null) ?: null) : null;
    }

    public function checklistItemCheckedAt(string $key): ?string
    {
        $item = $this->checklist[$key] ?? [];

        return is_array($item) && ! empty($item['checked_at'])
            ? \Illuminate\Support\Carbon::parse($item['checked_at'])->format('d M Y H:i')
            : null;
    }

    public function checklistItemCheckedBy(string $key): ?string
    {
        $item = $this->checklist[$key] ?? [];
        $userId = is_array($item) ? ($item['checked_by'] ?? null) : null;

        return $userId ? ($this->checklistUsers[$userId] ?? 'User #'.$userId) : null;
    }

    public function eligibilitySnapshotRows()
    {
        return collect($this->application->eligibility_snapshot ?? [])
            ->reject(fn ($value, $key) => is_array($value) || in_array($key, ['policy_id'], true));
    }

    public function eligibilitySnapshot(): array
    {
        return $this->application->eligibility_snapshot ?? [];
    }

    public function nextAction(): array
    {
        if ($this->application->status === 'submitted') {
            return [
                'tone' => 'primary',
                'icon' => 'fa-search',
                'title' => 'Mulai review pengajuan',
                'body' => 'Tandai under review atau mulai centang checklist. Centang checklist akan otomatis menyimpan progres dan mengubah status ke Under Review.',
            ];
        }

        if ($this->application->status === 'under_review') {
            if (! $this->checklistIsComplete()) {
                return [
                    'tone' => 'warning',
                    'icon' => 'fa-list-check',
                    'title' => 'Lengkapi checklist review',
                    'body' => 'Approve baru aktif setelah semua checklist yudisium selesai. Isi catatan per item bila ada temuan untuk audit internal.',
                ];
            }

            if (! $this->requiredDocumentsAreVerified()) {
                return [
                    'tone' => 'warning',
                    'icon' => 'fa-folder-open',
                    'title' => 'Verifikasi dokumen wajib',
                    'body' => 'Checklist sudah lengkap, tapi dokumen wajib yudisium masih ada yang belum verified atau ditolak.',
                ];
            }

            return [
                'tone' => 'success',
                'icon' => 'fa-check-circle',
                'title' => 'Pengajuan siap diapprove',
                'body' => 'Semua checklist sudah lengkap. Klik Approve jika hasil review akademik sudah sesuai.',
            ];
        }

        if ($this->application->status === 'revision_requested') {
            return [
                'tone' => 'warning',
                'icon' => 'fa-rotate-left',
                'title' => 'Menunggu perbaikan mahasiswa',
                'body' => 'Mahasiswa perlu memperbaiki pengajuan dari catatan admin. Setelah dikirim ulang, review bisa dilanjutkan.',
            ];
        }

        if ($this->application->status === 'approved') {
            if (! $this->application->graduationBatch?->yudisium_date) {
                return [
                    'tone' => 'danger',
                    'icon' => 'fa-calendar-xmark',
                    'title' => 'Tanggal yudisium batch belum diisi',
                    'body' => 'Finalize belum bisa dilakukan sampai batch yudisium memiliki tanggal resmi.',
                ];
            }

            return [
                'tone' => 'success',
                'icon' => 'fa-user-graduate',
                'title' => 'Siap finalize kelulusan',
                'body' => 'Finalize akan mengubah status mahasiswa menjadi Lulus dan menonaktifkan profil mahasiswa.',
            ];
        }

        if ($this->application->status === 'finalized') {
            return [
                'tone' => 'success',
                'icon' => 'fa-lock',
                'title' => 'Yudisium sudah final',
                'body' => 'Pengajuan sudah difinalisasi dan tidak bisa direvisi lewat workflow normal.',
            ];
        }

        if ($this->application->status === 'rejected') {
            return [
                'tone' => 'danger',
                'icon' => 'fa-circle-xmark',
                'title' => 'Pengajuan ditolak',
                'body' => 'Pengajuan sudah ditolak. Mahasiswa perlu membuat pengajuan baru jika kampus mengizinkan.',
            ];
        }

        return [
            'tone' => 'secondary',
            'icon' => 'fa-circle-info',
            'title' => 'Cek status pengajuan',
            'body' => 'Lihat detail status, checklist, dan history untuk menentukan aksi berikutnya.',
        ];
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function requiredDocumentsAreVerified(): bool
    {
        $this->application->loadMissing(['studentProfile', 'documents']);
        $documentsByRequirement = $this->application->documents->keyBy('graduation_document_requirement_id');

        return app(GraduationApplicationService::class)
            ->documentRequirements($this->application->studentProfile)
            ->filter(fn ($requirement) => $requirement->is_required)
            ->every(function ($requirement) use ($documentsByRequirement) {
                $document = $documentsByRequirement->get($requirement->id);

                return $document && $document->verification_status === 'verified';
            });
    }

    public function documentPreviewUrl(GraduationDocument $document): string
    {
        return route('admin.student-services.graduation-documents.preview', ['document' => $document->id]);
    }

    private function reload(): void
    {
        $this->application->refresh()->load([
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'finalizedBy',
            'academicPeriod',
            'graduationBatch',
            'documents.requirement',
            'documents.verifiedBy',
        ]);
        $this->checklist = $this->application->admin_checklist ?: app(GraduationApplicationService::class)->defaultChecklist();
        $this->graduationDate = $this->application->graduation_date?->format('Y-m-d') ?? $this->application->graduationBatch?->yudisium_date?->format('Y-m-d') ?? $this->graduationDate;
        $this->loadDocumentNotes();
        $this->loadChecklistUsers();
    }

    private function loadDocumentNotes(): void
    {
        $this->documentNotes = $this->application->documents
            ->mapWithKeys(fn (GraduationDocument $document) => [$document->id => $document->verification_notes])
            ->toArray();
    }

    private function loadChecklistUsers(): void
    {
        $ids = collect($this->checklist)
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

<div class="row">
    <div class="col-lg-8">
        <x-alert />

        @php($nextAction = $this->nextAction())
        <div class="alert alert-{{ $nextAction['tone'] }} d-flex gap-3 align-items-start">
            <i class="fas {{ $nextAction['icon'] }} mt-1"></i>
            <div>
                <div class="fw-bold">{{ $nextAction['title'] }}</div>
                <div>{{ $nextAction['body'] }}</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-0">{{ $application->application_number }}</h3>
                    <small class="text-muted">Pengajuan Yudisium</small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if ($application->student_profile_id)
                        <a href="{{ route('admin.academic.transcripts.show', ['id' => $application->student_profile_id]) }}" class="btn btn-outline-primary">
                            <i class="fas fa-file-alt me-1"></i> Transcript
                        </a>
                    @endif
                    <a href="{{ route('admin.student-services.graduation-applications.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted">Status</small>
                        <div><span class="badge {{ $this->statusBadge($application->status) }}">{{ $this->statusLabel($application->status) }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Mahasiswa</small>
                        <div class="h6 mb-0">{{ $application->studentProfile?->user?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">NIM</small>
                        <div class="h6 mb-0">{{ $application->studentProfile?->nim ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Program Studi</small>
                        <div>{{ $application->studentProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Periode Pendaftaran</small>
                        <div>{{ $application->graduation_period ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Batch Yudisium</small>
                        <div>{{ $application->graduationBatch?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Tanggal Yudisium Batch</small>
                        <div>{{ $application->graduationBatch?->yudisium_date?->format('d M Y') ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">SK Batch</small>
                        <div>{{ $application->graduationBatch?->sk_number ?: '-' }}{{ $application->graduationBatch?->sk_date ? ' / '.$application->graduationBatch->sk_date->format('d M Y') : '' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Tanggal Lulus Tercatat</small>
                        <div>{{ $application->graduation_date?->format('d M Y') ?? '-' }}</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Judul Skripsi / Tugas Akhir</small>
                        <div>{{ $application->thesis_title ?: '-' }}</div>
                    </div>
                    @if ($application->attachment_path)
                        <div class="col-12">
                            <a href="{{ $this->fileUrl($application->attachment_path) }}" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-paperclip me-1"></i> Preview Attachment
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Eligibility Snapshot</h3>
            </div>
            <div class="card-body">
                @php($snapshot = $this->eligibilitySnapshot())
                <div class="alert alert-info">
                    <div class="fw-bold">Policy yang dipakai</div>
                    <div>
                        {{ $snapshot['policy_name'] ?? 'Policy belum tersimpan di snapshot' }}
                        @if (! empty($snapshot['policy_scope']))
                            <span class="text-muted">({{ $snapshot['policy_scope'] }})</span>
                        @endif
                    </div>
                    <div class="small text-muted">Snapshot ini adalah kondisi saat pengajuan/review terakhir diproses.</div>
                </div>
                <div class="row g-3">
                    @foreach ($this->eligibilitySnapshotRows() as $key => $value)
                        <div class="col-md-4">
                            <small class="text-muted">{{ str($key)->replace('_', ' ')->title() }}</small>
                            <div class="fw-bold">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?: '-') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="card-title mb-0">Dokumen Yudisium</h3>
                    <small class="text-muted">Preview dan verifikasi dokumen yang diupload mahasiswa.</small>
                </div>
                <span class="badge bg-primary">{{ $application->documents->count() }} dokumen</span>
            </div>
            <div class="card-body">
                @forelse ($application->documents as $document)
                    <div class="border rounded p-3 mb-3 {{ $document->verification_status === 'verified' ? 'border-success bg-green-lt' : ($document->verification_status === 'rejected' ? 'border-danger bg-red-lt' : 'border-light') }}">
                        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-2">
                            <div>
                                <div class="fw-bold">{{ $document->requirement?->label ?? str($document->document_type)->replace('_', ' ')->title() }}</div>
                                <div class="small text-muted">
                                    {{ $document->file_name }} · {{ number_format(($document->file_size ?: 0) / 1024, 1) }} KB
                                </div>
                                @if ($document->verified_at)
                                    <div class="small text-muted">
                                        Dicek oleh {{ $document->verifiedBy?->name ?? 'Admin' }} pada {{ $document->verified_at?->format('d M Y H:i') }}.
                                    </div>
                                @endif
                            </div>
                            <span class="badge {{ $this->documentStatusBadge($document->verification_status) }}">
                                {{ $this->documentStatusLabel($document->verification_status) }}
                            </span>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label">Catatan Verifikasi</label>
                                <textarea wire:model="documentNotes.{{ $document->id }}" rows="2" class="form-control" placeholder="Catatan opsional untuk mahasiswa" @disabled(in_array($application->status, ['approved', 'finalized'], true))></textarea>
                            </div>
                            <div class="col-md-5">
                                <div class="d-flex gap-2 flex-wrap justify-content-md-end">
                                    <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank" class="btn btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> Preview
                                    </a>
                                    <button type="button" wire:click="verifyDocument({{ $document->id }}, 'verified')" class="btn btn-success" @disabled(in_array($application->status, ['approved', 'finalized'], true))>
                                        <i class="fas fa-check me-1"></i> Verify
                                    </button>
                                    <button type="button" wire:click="verifyDocument({{ $document->id }}, 'rejected')" class="btn btn-danger" @disabled(in_array($application->status, ['approved', 'finalized'], true))>
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Mahasiswa belum mengupload dokumen yudisium.</div>
                @endforelse
                <div class="alert alert-info mb-0">
                    Jika ada dokumen yang ditolak, gunakan <strong>Minta Perbaikan</strong> agar mahasiswa bisa upload ulang dokumen tanpa membuat pengajuan baru.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Status History</h3>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($application->histories as $history)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $this->statusLabel($history->to_status) }}</strong>
                            <span class="text-muted">{{ $history->created_at?->format('d M Y H:i') }}</span>
                        </div>
                        <div class="text-muted small">{{ $history->notes ?: '-' }}</div>
                        <div class="text-muted small">By {{ $history->changedBy?->name ?? 'System' }}</div>
                    </div>
                @empty
                    <div class="list-group-item text-muted">Belum ada history.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Review Actions</h3>
            </div>
            <div class="card-body">
                <label class="form-label">Admin Notes</label>
                <textarea wire:model="adminNotes" class="form-control mb-3" rows="4" placeholder="Catatan untuk mahasiswa"></textarea>

                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label mb-0">Checklist Review</label>
                        <span class="badge {{ $this->checklistIsComplete() ? 'bg-success' : 'bg-warning text-dark' }}">
                            {{ $this->checklistCompletedCount() }}/{{ count($this->checklistLabels()) }}
                        </span>
                    </div>
                    <div class="progress mb-3" style="height: 8px;">
                        <div class="progress-bar {{ $this->checklistIsComplete() ? 'bg-success' : 'bg-primary' }}" style="width: {{ count($this->checklistLabels()) ? ($this->checklistCompletedCount() / count($this->checklistLabels()) * 100) : 0 }}%;"></div>
                    </div>
                    @foreach ($this->checklistLabels() as $key => $label)
                        <label class="d-flex align-items-start gap-3 border rounded p-3 mb-2 {{ $this->checklistItemChecked($key) ? 'border-success bg-green-lt' : 'border-light bg-light' }}">
                            <input class="form-check-input mt-1" type="checkbox" wire:model.live="checklist.{{ $key }}.checked" @disabled(in_array($application->status, ['approved', 'finalized'], true))>
                            <span class="flex-fill">
                                <span class="fw-bold d-block">{{ $label }}</span>
                                <span class="small text-muted d-block">
                                    @if ($this->checklistItemChecked($key))
                                        Dicek oleh {{ $this->checklistItemCheckedBy($key) ?? '-' }}{{ $this->checklistItemCheckedAt($key) ? ' pada '.$this->checklistItemCheckedAt($key) : '' }}.
                                    @else
                                        Belum dicek.
                                    @endif
                                </span>
                                <textarea wire:model.live.debounce.700ms="checklist.{{ $key }}.notes" class="form-control form-control-sm mt-2" rows="2" placeholder="Catatan opsional" @disabled(in_array($application->status, ['approved', 'finalized'], true))></textarea>
                            </span>
                            <i class="fas {{ $this->checklistItemChecked($key) ? 'fa-check-circle text-success' : 'fa-circle text-muted' }} mt-1"></i>
                        </label>
                    @endforeach
                    <div class="small text-muted mt-2">
                        Centang checklist akan autosave dan otomatis memindahkan status ke <strong>Under Review</strong>.
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button wire:click="markUnderReview" class="btn btn-outline-primary" @disabled(! in_array($application->status, ['submitted', 'revision_requested'], true))>
                        <i class="fas fa-search me-1"></i> Mark Under Review
                    </button>
                    <button wire:click="approve" class="btn btn-success" @disabled(! $this->checklistIsComplete() || ! $this->requiredDocumentsAreVerified() || ! in_array($application->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        <i class="fas fa-check me-1"></i> Approve
                    </button>
                    @if ((! $this->checklistIsComplete() || ! $this->requiredDocumentsAreVerified()) && in_array($application->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))
                        <div class="small text-muted text-center">
                            Approve aktif setelah semua checklist lengkap dan dokumen wajib verified.
                        </div>
                    @endif
                    <button wire:click="requestCorrection" class="btn btn-warning" @disabled(! in_array($application->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        <i class="fas fa-rotate-left me-1"></i> Minta Perbaikan
                    </button>
                    <button wire:click="reject" class="btn btn-danger" @disabled(! in_array($application->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        <i class="fas fa-times me-1"></i> Reject
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Finalisasi Yudisium</h3>
            </div>
            <div class="card-body d-grid gap-2">
                <label class="form-label">Tanggal Yudisium Batch</label>
                <input type="text" class="form-control" value="{{ $application->graduationBatch?->yudisium_date?->format('d M Y') ?? 'Belum diatur pada batch' }}" disabled>
                <button wire:click="finalize" class="btn btn-primary" @disabled($application->status !== 'approved' || ! $application->graduationBatch?->yudisium_date)>
                    <i class="fas fa-user-graduate me-1"></i> Finalisasi Yudisium
                </button>
                @if ($application->status === 'approved' && ! $application->graduationBatch?->yudisium_date)
                    <div class="alert alert-danger mb-0">
                        Tanggal yudisium batch wajib diisi sebelum finalize. Edit batch yudisium terlebih dulu.
                    </div>
                @endif
                <div class="alert alert-info mb-0">
                    Finalisasi per mahasiswa memakai tanggal yudisium dari batch. Untuk banyak mahasiswa, gunakan finalisasi massal dari halaman Batch Yudisium.
                </div>
            </div>
        </div>
    </div>
</div>
