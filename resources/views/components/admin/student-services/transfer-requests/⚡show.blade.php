<?php

use App\Models\StudentService\StudentTransferRequest;
use App\Support\ActivePermission;
use App\Support\StudentService\StudentTransferRequestService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public StudentTransferRequest $request;

    public array $evaluation = [
        'admin_notes' => '',
        'academic_evaluation_notes' => '',
        'credit_mapping_notes' => '',
        'finance_notes' => '',
        'recommended_semester' => null,
    ];

    public $transferFeeAmount = 0;

    public ?string $transferFeeDueDate = null;

    public function mount($id): void
    {
        $this->request = StudentTransferRequest::with([
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'fromStudyProgram.faculty',
            'toStudyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'appliedBy',
            'transferFeeInvoice',
            'approvalRequest.steps.actedBy',
        ])->findOrFail($id);

        $this->evaluation = [
            'admin_notes' => $this->request->admin_notes ?? '',
            'academic_evaluation_notes' => $this->request->academic_evaluation_notes ?? '',
            'credit_mapping_notes' => $this->request->credit_mapping_notes ?? '',
            'finance_notes' => $this->request->finance_notes ?? '',
            'recommended_semester' => $this->request->recommended_semester,
        ];
        $this->transferFeeAmount = (float) $this->request->transfer_fee_amount;
        $this->transferFeeDueDate = $this->request->transfer_fee_due_date?->format('Y-m-d') ?? now()->addDays(7)->toDateString();
    }

    public function markUnderReview(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);
        $this->request = $service->setStatus($this->request, 'under_review', $this->evaluation['admin_notes'] ?: null, auth()->id());
        session()->flash('success', 'Pengajuan transfer ditandai under review.');
        $this->reload();
    }

    public function approve(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);

        $validated = $this->validate([
            'evaluation.admin_notes' => ['nullable', 'string', 'max:3000'],
            'evaluation.academic_evaluation_notes' => ['nullable', 'string', 'max:3000'],
            'evaluation.credit_mapping_notes' => ['nullable', 'string', 'max:3000'],
            'evaluation.finance_notes' => ['nullable', 'string', 'max:3000'],
            'evaluation.recommended_semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'transferFeeAmount' => ['required', 'numeric', 'min:0'],
            'transferFeeDueDate' => ['nullable', 'date'],
        ]);

        if ((float) $this->transferFeeAmount > 0 && ! $this->transferFeeDueDate) {
            session()->flash('error', 'Due date wajib diisi jika transfer dikenakan biaya.');

            return;
        }

        try {
            $this->request = $service->approve(
                $this->request,
                $validated['evaluation'],
                auth()->id(),
                (float) $this->transferFeeAmount,
                $this->transferFeeDueDate,
            );

            session()->flash(
                'success',
                (float) $this->transferFeeAmount > 0
                    ? 'Pengajuan transfer diapprove dan invoice biaya transfer diterbitkan.'
                    : 'Pengajuan transfer berhasil diapprove.',
            );
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function requestCorrection(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);
        $this->request = $service->setStatus($this->request, 'revision_requested', $this->evaluation['admin_notes'] ?: null, auth()->id());
        session()->flash('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
        $this->reload();
    }

    public function reject(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);
        try {
            $this->request = $service->reject($this->request, $this->evaluation['admin_notes'] ?: null, auth()->id());
            session()->flash('success', 'Pengajuan transfer ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
        $this->reload();
    }

    public function applyTransfer(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);

        try {
            $this->request = $service->applyTransfer($this->request, auth()->id(), $this->evaluation['admin_notes'] ?: null);
            session()->flash('success', 'Transfer mahasiswa berhasil diterapkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Detail Pengajuan Pindah',
        ]);
    }

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'applied' => 'bg-success',
            'approved' => 'bg-info',
            'approved_pending_payment', 'revision_requested', 'in_approval' => 'bg-warning text-dark',
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
            'approved_pending_payment' => 'Menunggu Pembayaran',
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
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'fromStudyProgram.faculty',
            'toStudyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'appliedBy',
            'transferFeeInvoice',
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
        title="Pengajuan Pindah Internal / Konversi Program"
        description="Nomor Pengajuan: {{ $request->request_number }} • Mahasiswa: {{ $request->studentProfile?->user?->name ?? '-' }} ({{ $request->studentProfile?->nim ?? '-' }})"
        icon="exchange-alt"
    >
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.student-services.transfer-requests.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-info fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Pengajuan</div>
                        <div class="fw-bold">{{ $this->statusLabel($request->status) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-random fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Jenis Pindah</div>
                        <div class="fw-bold">{{ str($request->transfer_type)->replace('_', ' ')->title() }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-arrow-right fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tujuan Pindah</div>
                        <div class="fw-bold">{{ $request->targetStudyProgram?->name ?? ($request->target_class_shift ? str($request->target_class_shift)->title() : '-') }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-school fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Prodi Asal</div>
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
                <h4 class="card-title fw-bold mb-0">Informasi Pindah Program / Kelas</h4>
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
                    <div class="col-md-6">
                        <small class="text-muted">{{ $request->transfer_type === 'class_type' ? 'Kelas Saat Ini' : 'Program Asal (From)' }}</small>
                        <div class="fw-semibold text-danger">{{ $request->transfer_type === 'class_type' ? ucfirst($request->from_class_type ?? '-') : ($request->fromStudyProgram?->name ?? '-') }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ $request->transfer_type === 'class_type' ? 'Kelas Tujuan' : 'Program Tujuan (To)' }}</small>
                        <div class="fw-semibold text-success">{{ $request->transfer_type === 'class_type' ? ucfirst($request->to_class_type ?? '-') : ($request->toStudyProgram?->name ?? '-') }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Jenis Perpindahan</small>
                        <div>{{ str($request->transfer_type)->replace('_', ' ')->title() }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Semester Saat Ini</small>
                        <div>{{ $request->current_semester ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Rekomendasi Semester Tujuan</small>
                        <div>{{ $request->recommended_semester ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Biaya Administrasi Transfer</small>
                        <div>Rp {{ number_format((float) $request->transfer_fee_amount, 0, ',', '.') }}</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Alasan Kepindahan</small>
                        <div>{{ $request->reason }}</div>
                    </div>
                    @if ($request->student_notes)
                        <div class="col-12">
                            <small class="text-muted">Catatan dari Mahasiswa</small>
                            <div>{{ $request->student_notes }}</div>
                        </div>
                    @endif
                    @if ($request->transferFeeInvoice)
                        <div class="col-12">
                            <div class="alert alert-warning mb-0">
                                Invoice Biaya Transfer:
                                <a href="{{ route('admin.financial.student-invoices.show', ['id' => $request->transferFeeInvoice->id]) }}" class="fw-bold">
                                    {{ $request->transferFeeInvoice->invoice_number }}
                                </a>
                                <span class="badge bg-light text-dark ms-2">{{ $request->transferFeeInvoice->status }}</span>
                            </div>
                        </div>
                    @endif
                    @if ($request->attachment_path)
                        <div class="col-12">
                            <a href="{{ $this->fileUrl($request->attachment_path) }}" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-paperclip me-1"></i> Lihat Lampiran Pengajuan
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card soft-card">
            <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Catatan Evaluasi Akademik & Konversi</h4>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Rekomendasi Penempatan Semester</label>
                        <input type="number" min="1" max="14" class="form-control" wire:model="evaluation.recommended_semester">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Biaya Transfer (Rp)</label>
                        <input type="number" min="0" step="0.01" class="form-control" wire:model="transferFeeAmount" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Batas Pembayaran (Due Date)</label>
                        <input type="date" class="form-control" wire:model="transferFeeDueDate" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Catatan Umum Admin</label>
                        <textarea class="form-control" rows="3" wire:model="evaluation.admin_notes" placeholder="Catatan untuk mahasiswa atau internal"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Evaluasi Akademik (Kaprodi/Dekan)</label>
                        <textarea class="form-control" rows="3" wire:model="evaluation.academic_evaluation_notes" placeholder="Evaluasi kesesuaian akademik..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Catatan Pemetaan & Konversi SKS</label>
                        <textarea class="form-control" rows="3" wire:model="evaluation.credit_mapping_notes" placeholder="Daftar mata kuliah yang diakui / dikonversi..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Catatan Bagian Keuangan</label>
                        <textarea class="form-control" rows="3" wire:model="evaluation.finance_notes" placeholder="Pengecekan bebas tunggakan biaya kuliah..."></textarea>
                    </div>
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
            <div class="card-body p-4 d-grid gap-2">
                <button wire:click="markUnderReview" class="btn btn-outline-primary fw-bold py-2" @disabled(! in_array($request->status, ['submitted', 'revision_requested'], true))>
                    <i class="fas fa-search me-1"></i> Tandai Sedang Direview
                </button>
                <button wire:click="approve" class="btn btn-success fw-bold py-2" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    <i class="fas fa-check me-1"></i> Setujui Pengajuan (Approve)
                </button>
                <button wire:click="requestCorrection" class="btn btn-warning fw-bold text-dark py-2" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    <i class="fas fa-rotate-left me-1"></i> Minta Perbaikan Mahasiswa
                </button>
                <button wire:click="reject" class="btn btn-danger fw-bold py-2" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    <i class="fas fa-times me-1"></i> Tolak Pengajuan
                </button>
            </div>
        </div>

        <div class="card soft-card">
            <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Terapkan Perpindahan (Apply)</h4>
            </div>
            <div class="card-body p-4 d-grid gap-2">
                <button wire:click="applyTransfer" class="btn btn-primary fw-bold py-2" @disabled(! in_array($request->status, ['approved', 'approved_pending_payment'], true) || ($request->transferFeeInvoice && $request->transferFeeInvoice->status !== 'paid'))>
                    <i class="fas fa-right-left me-1"></i> Terapkan & Pindahkan Mahasiswa
                </button>
                <div class="alert alert-info mb-0 mt-2">
                    <strong>Terapkan Pindah</strong> akan memperbarui profil mahasiswa ke program studi atau kelas tujuan secara permanen.
                </div>
            </div>
        </div>
    </div>
</div>
