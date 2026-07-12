<?php

use App\Models\StudentService\ServiceLetterRequest;
use App\Support\ActivePermission;
use App\Support\StudentService\ServiceLetterRequestService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ServiceLetterRequest $request;

    public ?string $adminNotes = null;

    public string $issueMethod = 'auto_generate';

    public ?TemporaryUploadedFile $finalFile = null;

    public function mount($id): void
    {
        $this->request = ServiceLetterRequest::with([
            'letterType',
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'issuedBy',
            'approvalRequest.steps.actedBy',
        ])->findOrFail($id);

        $this->adminNotes = $this->request->admin_notes;
        $this->issueMethod = $this->request->letterType->fulfillment_mode === 'manual_upload'
            ? 'manual_upload'
            : 'auto_generate';
    }

    public function markUnderReview(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);
        $this->request = $service->setStatus($this->request, 'under_review', $this->adminNotes, auth()->id());
        session()->flash('success', 'Request ditandai under review.');
        $this->reload();
    }

    public function approve(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);
        try {
            $this->request = $service->approve($this->request, $this->adminNotes, auth()->id());
            session()->flash('success', 'Request berhasil diapprove.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
        $this->reload();
    }

    public function requestRevision(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);
        $this->request = $service->setStatus($this->request, 'revision_requested', $this->adminNotes, auth()->id());
        session()->flash('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
        $this->reload();
    }

    public function reject(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);
        try {
            $this->request = $service->reject($this->request, $this->adminNotes, auth()->id());
            session()->flash('success', 'Request ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
        $this->reload();
    }

    public function issue(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);

        $this->validate([
            'issueMethod' => ['required', Rule::in(['auto_generate', 'manual_upload'])],
            'finalFile' => [$this->issueMethod === 'manual_upload' ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        try {
            $this->request = $service->issue($this->request, $this->issueMethod, $this->finalFile, auth()->id());
            $this->finalFile = null;
            session()->flash('success', 'Surat berhasil diterbitkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Detail Pengajuan Surat',
        ]);
    }

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'issued' => 'bg-success',
            'approved' => 'bg-info',
            'under_review' => 'bg-primary',
            'revision_requested', 'in_approval' => 'bg-warning text-dark',
            'rejected', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'in_approval' => 'Menunggu Approval',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function reload(): void
    {
        $this->request->refresh()->load([
            'letterType',
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'issuedBy',
            'approvalRequest.steps.actedBy',
        ]);
    }
};
?>

@push('styles')
    <style>
        .service-show .soft-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, .08);
            margin-bottom: 1.5rem;
        }
    </style>
@endpush

<div class="w-full service-show" style="width: 100% !important">
    <x-alert />

    <x-admin.student-services.header
        title="{{ $request->letterType?->name ?? 'Layanan Permohonan Surat' }}"
        description="Nomor Pengajuan: {{ $request->request_number }} • Mahasiswa: {{ $request->studentProfile?->user?->name ?? '-' }} ({{ $request->studentProfile?->nim ?? '-' }})"
        icon="file-signature"
    >
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.student-services.letter-requests.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-info fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Permohonan</div>
                        <div class="fw-bold">{{ $this->statusLabel($request->status) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-file-contract fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Mode Penerbitan</div>
                        <div class="fw-bold">{{ str($request->letterType?->fulfillment_mode)->replace('_', ' ')->title() }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tanggal Diajukan</div>
                        <div class="fw-bold">{{ $request->created_at?->format('d M Y H:i') ?? '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-school fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Program Studi</div>
                        <div class="fw-bold">{{ $request->studentProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card soft-card">
            <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Informasi Permohonan Surat</h4>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted">Status</small>
                        <div><span class="badge {{ $this->statusBadge($request->status) }}">{{ $this->statusLabel($request->status) }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Mahasiswa</small>
                        <div class="h6 mb-0">{{ $request->studentProfile?->user?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">NIM</small>
                        <div class="h6 mb-0">{{ $request->studentProfile?->nim ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Program Studi</small>
                        <div>{{ $request->studentProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Submitted</small>
                        <div>{{ $request->created_at?->format('d M Y H:i') }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Mode Penerbitan</small>
                        <div>{{ str($request->letterType?->fulfillment_mode)->replace('_', ' ')->title() }}</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Keperluan (Purpose)</small>
                        <div>{{ $request->purpose }}</div>
                    </div>
                    @if ($request->request_data)
                        <div class="col-12">
                            <small class="text-muted">Data Tambahan Permohonan</small>
                            <div class="border rounded p-3 bg-light mt-1">
                                @foreach ($request->request_data as $key => $value)
                                    <div class="d-flex justify-content-between border-bottom py-1">
                                        <span class="text-muted">{{ str($key)->replace('_', ' ')->title() }}</span>
                                        <strong>{{ is_array($value) ? json_encode($value) : $value }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($request->attachment_path)
                        <div class="col-12">
                            <a href="{{ $this->fileUrl($request->attachment_path) }}" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-paperclip me-1"></i> Lihat Lampiran Permohonan
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card soft-card">
            <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Riwayat Perubahan Status</h4>
            </div>
            <div class="list-group list-group-flush pt-2">
                @forelse ($request->histories as $history)
                    <div class="list-group-item px-4 py-3">
                        <div class="d-flex justify-content-between">
                            <strong class="text-dark">{{ $this->statusLabel($history->to_status) }}</strong>
                            <span class="text-muted small">{{ $history->created_at?->format('d M Y H:i') }}</span>
                        </div>
                        <div class="text-muted small mt-1">{{ $history->notes ?: '-' }}</div>
                        <div class="text-muted small">Oleh {{ $history->changedBy?->name ?? 'System' }}</div>
                    </div>
                @empty
                    <div class="list-group-item text-muted px-4 py-3">Belum ada riwayat perubahan status.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card soft-card mb-4">
            <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Tindakan Evaluasi (Review)</h4>
            </div>
            <div class="card-body p-4">
                <label class="form-label fw-semibold">Catatan Admin / Operator</label>
                <textarea wire:model="adminNotes" class="form-control mb-3" rows="4" placeholder="Tuliskan catatan untuk mahasiswa..."></textarea>

                <div class="d-grid gap-2">
                    <button wire:click="markUnderReview" class="btn btn-outline-primary fw-bold" @disabled(! in_array($request->status, ['submitted', 'revision_requested'], true))>
                        <i class="fas fa-search me-1"></i> Tandai Sedang Direview
                    </button>
                    <button wire:click="approve" class="btn btn-success fw-bold" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        <i class="fas fa-check me-1"></i> Setujui Permohonan (Approve)
                    </button>
                    <button wire:click="requestRevision" class="btn btn-warning fw-bold text-dark" @disabled(in_array($request->status, ['issued', 'rejected', 'cancelled'], true))>
                        <i class="fas fa-rotate-left me-1"></i> Minta Perbaikan Mahasiswa
                    </button>
                    <button wire:click="reject" class="btn btn-danger fw-bold" @disabled(in_array($request->status, ['issued', 'rejected', 'cancelled'], true))>
                        <i class="fas fa-times me-1"></i> Tolak Permohonan
                    </button>
                </div>
            </div>
        </div>

        <div class="card soft-card">
            <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Penerbitan Surat (Issue)</h4>
            </div>
            <div class="card-body p-4">
                @if ($request->isDownloadable())
                    <a href="{{ route('admin.student-services.letter-requests.download', ['request' => $request->id]) }}" target="_blank" class="btn btn-success fw-bold w-100 mb-3 py-2">
                        <i class="fas fa-download me-1"></i> Download Surat Terbit
                    </a>
                @endif

                <label class="form-label fw-semibold">Metode Penerbitan</label>
                <select wire:model.live="issueMethod" class="form-select mb-3">
                    @if (in_array($request->letterType?->fulfillment_mode, ['auto_generate', 'hybrid'], true))
                        <option value="auto_generate">Auto Generate PDF (Otomatis)</option>
                    @endif
                    @if (in_array($request->letterType?->fulfillment_mode, ['manual_upload', 'hybrid'], true))
                        <option value="manual_upload">Manual Upload (Unggah Mandiri)</option>
                    @endif
                </select>

                @if ($issueMethod === 'manual_upload')
                    <label class="form-label fw-semibold">Berkas Surat Final (PDF/Gambar)</label>
                    <input type="file" wire:model="finalFile" class="form-control mb-2">
                    @error('finalFile') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                @endif

                <button wire:click="issue" class="btn btn-primary fw-bold w-100 py-2" @disabled($request->status !== 'approved')>
                    <i class="fas fa-paper-plane me-1"></i> Terbitkan & Kirim Surat
                </button>
            </div>
        </div>
    </div>
</div>
