<?php

use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\Organization\ApprovalEngine;
use Livewire\Component;

new class extends Component
{
    public LecturerWorkloadSubmission $submission;
    public string $revisionNotes = '';

    public function mount($id): void
    {
        $this->submission = LecturerWorkloadSubmission::with(['period', 'owner', 'items', 'approvalRequest.steps.actedBy'])->findOrFail($id);
        $this->revisionNotes = $this->submission->review_notes ?: 'Dikembalikan untuk revisi oleh admin.';
    }

    public function requestRevision(ApprovalEngine $approvalEngine): void
    {
        if (! in_array($this->submission->status, ['draft', 'in_approval', 'submitted'], true)) {
            session()->flash('error', 'Status BKD tidak bisa dikembalikan ke revisi.');
            return;
        }

        if (! $this->submission->period?->isOpen()) {
            session()->flash('error', 'Revisi hanya bisa diminta saat periode BKD masih dibuka.');
            return;
        }

        $notes = trim($this->revisionNotes) ?: 'Dikembalikan untuk revisi oleh admin.';

        if ($this->submission->approvalRequest && $this->submission->approvalRequest->status === 'in_progress') {
            $approvalEngine->cancel($this->submission->approvalRequest, auth()->user(), $notes);
        }

        $this->submission->update([
            'approval_request_id' => null,
            'status' => 'revision',
            'review_notes' => $notes,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'BKD dikembalikan untuk revisi.');
        $this->submission = $this->submission->fresh(['period', 'owner', 'items', 'approvalRequest.steps.actedBy']);
        $this->revisionNotes = $this->submission->review_notes ?: 'Dikembalikan untuk revisi oleh admin.';
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Detail BKD']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Laporan BKD: {{ $submission->owner?->name ?? 'Dosen' }}"
        description="Periode: {{ $submission->period?->name ?? '-' }} &bull; Kode Periode: {{ $submission->period?->code ?? '-' }}"
        icon="file-alt"
    >
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('admin.organization.lecturer-workload-submissions.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
            @activecan('lecturer-workload-submission.update')
                @if (in_array($submission->status, ['draft', 'in_approval', 'submitted'], true))
                    <button type="button" class="btn btn-sm btn-light text-warning fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="requestRevision" wire:confirm="Kembalikan laporan ini kepada dosen untuk revisi?">
                        <i class="fa fa-rotate-left"></i> <span>Kembalikan untuk Revisi</span>
                    </button>
                @endif
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-info fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Laporan</div>
                        <div class="fw-bold">{{ str($submission->status)->replace('_', ' ')->title() }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-chalkboard-user fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">SKS Pengajaran</div>
                        <div class="fw-bold">{{ number_format((float) $submission->teaching_sks, 1) }} SKS</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-sitemap fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">SKS Struktural</div>
                        <div class="fw-bold">{{ number_format((float) $submission->structural_sks, 1) }} SKS</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calculator fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total SKS Diakui</div>
                        <div class="fw-bold">{{ number_format((float) $submission->total_sks, 1) }} SKS</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    @if ($submission->review_notes)
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4 d-flex align-items-start gap-3">
            <i class="fa fa-triangle-exclamation fs-3 text-warning mt-1"></i>
            <div>
                <h6 class="fw-bold text-dark mb-1">Catatan Hasil Review / Revisi</h6>
                <p class="mb-0 text-dark">{{ $submission->review_notes }}</p>
            </div>
        </div>
    @endif

    @activecan('lecturer-workload-submission.update')
        @if (in_array($submission->status, ['draft', 'in_approval', 'submitted'], true))
            <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-warning border-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="fa fa-edit fs-6"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-0">Catatan Instruksi Revisi untuk Dosen</h6>
                    </div>
                    <textarea class="form-control rounded-3" rows="3" wire:model.defer="revisionNotes" placeholder="Tuliskan bagian atau butir kegiatan yang perlu diperbaiki oleh dosen yang bersangkutan..."></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                        <span class="small text-muted"><i class="fa fa-circle-info me-1"></i>Catatan ini akan langsung ditampilkan di dasbor BKD dosen setelah tombol "Kembalikan untuk Revisi" ditekan.</span>
                        <button type="button" class="btn btn-warning rounded-pill px-4 fw-medium shadow-sm d-inline-flex align-items-center gap-2" wire:click="requestRevision" wire:confirm="Kembalikan laporan ini kepada dosen untuk revisi sekarang?">
                            <i class="fa fa-rotate-left"></i> <span>Kirim Permintaan Revisi</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endactivecan

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-list-check fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Rincian Butir Kegiatan Tridharma & Tugas Tambahan</h4>
                    <span class="text-muted small">Daftar seluruh item aktivitas yang diklaim dan dihitung sebagai beban kerja dalam semester/periode ini.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0 pt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-secondary text-uppercase small" style="width: 20%;">Kategori Aktivitas</th>
                            <th class="py-3 text-secondary text-uppercase small">Nama Butir / Judul Kegiatan</th>
                            <th class="py-3 text-center text-secondary text-uppercase small" style="width: 15%;">Kode Sumber</th>
                            <th class="pe-4 py-3 text-end text-secondary text-uppercase small" style="width: 15%;">Bobot SKS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($submission->items as $item)
                            <tr class="border-bottom">
                                <td class="ps-4 py-3">
                                    <span class="badge bg-light text-primary border rounded-pill px-3 py-1">
                                        {{ str($item->category)->title() }}
                                    </span>
                                </td>
                                <td class="py-3 fw-bold text-dark">{{ $item->title }}</td>
                                <td class="py-3 text-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-dark px-2 py-1">{{ $item->source_code }}</span>
                                </td>
                                <td class="pe-4 py-3 text-end fw-bold text-primary fs-6">{{ number_format((float) $item->sks, 2) }} SKS</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="fas fa-list-ul fs-3 d-block mb-2 text-secondary"></i>
                                    Tidak ada rincian butir kegiatan yang tercatat dalam laporan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-light fw-bold">
                        <tr>
                            <td colspan="3" class="ps-4 py-3 text-end text-dark">Total SKS Keseluruhan:</td>
                            <td class="pe-4 py-3 text-end text-primary fs-5">{{ number_format((float) $submission->total_sks, 2) }} SKS</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
