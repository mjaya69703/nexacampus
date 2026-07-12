<?php

use App\Models\StudentService\StudentLeaveApplication;
use App\Support\ActivePermission;
use App\Support\StudentService\StudentLeaveApplicationService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public StudentLeaveApplication $application;

    public ?string $adminNotes = null;

    public $leaveFeeAmount = 0;

    public ?string $leaveFeeDueDate = null;

    public function mount($id): void
    {
        $this->application = StudentLeaveApplication::with([
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'academicYear',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'activatedBy',
            'returnedBy',
            'leaveFeeInvoice',
            'approvalRequest.steps.actedBy',
        ])->findOrFail($id);

        $this->adminNotes = $this->application->admin_notes;
        $this->leaveFeeAmount = (float) $this->application->leave_fee_amount;
        $this->leaveFeeDueDate = $this->application->leave_fee_due_date?->format('Y-m-d') ?? now()->addDays(7)->toDateString();
    }

    public function markUnderReview(StudentLeaveApplicationService $service): void
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);
        $this->application = $service->setStatus($this->application, 'under_review', $this->adminNotes, auth()->id());
        session()->flash('success', 'Pengajuan ditandai under review.');
        $this->reload();
    }

    public function approve(StudentLeaveApplicationService $service): void
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);

        $this->validate([
            'leaveFeeAmount' => ['required', 'numeric', 'min:0'],
            'leaveFeeDueDate' => ['nullable', 'date'],
        ]);

        if ($this->leaveFeeAmount > 0 && ! $this->leaveFeeDueDate) {
            session()->flash('error', 'Due date wajib diisi jika cuti dikenakan biaya.');

            return;
        }

        try {
            $this->application = $service->approve(
                $this->application,
                $this->adminNotes,
                auth()->id(),
                $this->leaveFeeAmount,
                $this->leaveFeeDueDate,
            );

            session()->flash(
                'success',
                $this->leaveFeeAmount > 0
                    ? 'Pengajuan cuti diapprove dan invoice biaya cuti diterbitkan.'
                    : 'Pengajuan cuti berhasil diapprove.',
            );
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function requestCorrection(StudentLeaveApplicationService $service): void
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);
        $this->application = $service->setStatus($this->application, 'revision_requested', $this->adminNotes, auth()->id());
        session()->flash('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
        $this->reload();
    }

    public function reject(StudentLeaveApplicationService $service): void
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);
        try {
            $this->application = $service->reject($this->application, $this->adminNotes, auth()->id());
            session()->flash('success', 'Pengajuan cuti ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
        $this->reload();
    }

    public function activate(StudentLeaveApplicationService $service): void
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);

        try {
            $this->application = $service->activate($this->application, auth()->id(), $this->adminNotes);
            session()->flash('success', 'Status cuti mahasiswa berhasil diaktifkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function returnToActive(StudentLeaveApplicationService $service): void
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);

        try {
            $this->application = $service->returnToActive($this->application, auth()->id(), $this->adminNotes);
            session()->flash('success', 'Mahasiswa berhasil dikembalikan ke status aktif.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Detail Pengajuan Cuti',
        ]);
    }

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'returned', 'activated' => 'bg-success',
            'approved' => 'bg-info',
            'approved_pending_payment' => 'bg-warning text-dark',
            'in_approval' => 'bg-warning text-dark',
            'under_review' => 'bg-primary',
            'revision_requested' => 'bg-warning text-dark',
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
            'activated' => 'Cuti Aktif',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function reload(): void
    {
        $this->application->refresh()->load([
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'academicYear',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'activatedBy',
            'returnedBy',
            'leaveFeeInvoice',
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
        title="Pengajuan Cuti Akademik"
        description="Nomor Pengajuan: {{ $application->application_number }} • Mahasiswa: {{ $application->studentProfile?->user?->name ?? '-' }} ({{ $application->studentProfile?->nim ?? '-' }})"
        icon="calendar-minus"
    >
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.student-services.leave-applications.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-info fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Pengajuan</div>
                        <div class="fw-bold">{{ $this->statusLabel($application->status) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tahun Akademik</div>
                        <div class="fw-bold">{{ $application->academicYear?->name ?? '-' }} ({{ $application->semester ?? '-' }})</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Durasi Cuti</div>
                        <div class="fw-bold">{{ $application->duration_semesters }} Semester</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-school fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Program Studi</div>
                        <div class="fw-bold">{{ $application->studentProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card soft-card">
            <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Informasi Mahasiswa & Cuti</h4>
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
                        <small class="text-muted">Academic Year</small>
                        <div>{{ $application->academicYear?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Semester</small>
                        <div>{{ $application->semester ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Duration</small>
                        <div>{{ $application->duration_semesters }} semester</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Reason Category</small>
                        <div>{{ str($application->reason_category)->replace('_', ' ')->title() }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Current Student Status</small>
                        <div>{{ $application->studentProfile?->academic_status ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Biaya Cuti</small>
                        <div>Rp {{ number_format((float) $application->leave_fee_amount, 0, ',', '.') }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Invoice Biaya Cuti</small>
                        <div>
                            @if ($application->leaveFeeInvoice)
                                <a href="{{ route('admin.financial.student-invoices.show', ['id' => $application->leaveFeeInvoice->id]) }}">
                                    {{ $application->leaveFeeInvoice->invoice_number }}
                                </a>
                                <span class="badge bg-light text-dark">{{ $application->leaveFeeInvoice->status }}</span>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Reason</small>
                        <div>{{ $application->reason }}</div>
                    </div>
                    @if ($application->student_notes)
                        <div class="col-12">
                            <small class="text-muted">Student Notes</small>
                            <div>{{ $application->student_notes }}</div>
                        </div>
                    @endif
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

        <div class="card soft-card">
            <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Riwayat Status & Evaluasi</h4>
            </div>
            <div class="list-group list-group-flush pt-2">
                @forelse ($application->histories as $history)
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
                <textarea wire:model="adminNotes" class="form-control mb-3" rows="4" placeholder="Tuliskan catatan atau instruksi perbaikan untuk mahasiswa..."></textarea>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Biaya Cuti (Rp)</label>
                        <input type="number" min="0" step="0.01" class="form-control" wire:model="leaveFeeAmount" @disabled(! in_array($application->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        @error('leaveFeeAmount') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Batas Bayar (Due Date)</label>
                        <input type="date" class="form-control" wire:model="leaveFeeDueDate" @disabled(! in_array($application->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        @error('leaveFeeDueDate') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Isi 0 jika cuti tidak dikenakan biaya tagihan. Jika ada biaya, invoice langsung diterbitkan secara otomatis dan cuti baru bisa diaktifkan setelah lunas.</small>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button wire:click="markUnderReview" class="btn btn-outline-primary fw-bold" @disabled(! in_array($application->status, ['submitted', 'revision_requested'], true))>
                        <i class="fas fa-search me-1"></i> Tandai Sedang Direview
                    </button>
                    <button wire:click="approve" class="btn btn-success fw-bold" @disabled(! in_array($application->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        <i class="fas fa-check me-1"></i> Setujui Pengajuan (Approve)
                    </button>
                    <button wire:click="requestCorrection" class="btn btn-warning fw-bold text-dark" @disabled(! in_array($application->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        <i class="fas fa-rotate-left me-1"></i> Minta Perbaikan Mahasiswa
                    </button>
                    <button wire:click="reject" class="btn btn-danger fw-bold" @disabled(! in_array($application->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        <i class="fas fa-times me-1"></i> Tolak Pengajuan
                    </button>
                </div>
            </div>
        </div>

        <div class="card soft-card">
            <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Status & Pengaktifan Cuti</h4>
            </div>
            <div class="card-body p-4 d-grid gap-2">
                <button wire:click="activate" class="btn btn-primary fw-bold py-2" @disabled(! in_array($application->status, ['approved', 'approved_pending_payment'], true) || ($application->leaveFeeInvoice && $application->leaveFeeInvoice->status !== 'paid'))
                    title="{{ $application->leaveFeeInvoice && $application->leaveFeeInvoice->status !== 'paid' ? 'Biaya cuti harus lunas dulu.' : '' }}">
                    <i class="fas fa-calendar-minus me-1"></i> Aktifkan Status Cuti Mahasiswa
                </button>
                <button wire:click="returnToActive" class="btn btn-success fw-bold py-2" @disabled($application->status !== 'activated')>
                    <i class="fas fa-user-check me-1"></i> Kembalikan Ke Status Aktif
                </button>
                <div class="alert alert-info mb-0 mt-2">
                    <strong>Aktifkan Status Cuti</strong> akan otomatis mengubah status profil akademik mahasiswa menjadi <strong>Cuti</strong> pada masa perkuliahan ini.
                </div>
            </div>
        </div>
    </div>
</div>
