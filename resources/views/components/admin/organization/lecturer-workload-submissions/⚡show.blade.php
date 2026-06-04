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
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h3 class="card-title mb-0">BKD {{ $submission->owner?->name }}</h3>
                <small class="text-muted">{{ $submission->period?->name }} / {{ str($submission->status)->replace('_', ' ')->title() }}</small>
            </div>
            <div class="d-flex gap-2">
                @activecan('lecturer-workload-submission.update')
                    <button type="button" class="btn btn-warning" wire:click="requestRevision"><i class="fas fa-rotate-left me-2"></i>Minta Revisi</button>
                @endactivecan
                <a href="{{ route('admin.organization.lecturer-workload-submissions.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
            </div>
        </div>
    </div>
    @activecan('lecturer-workload-submission.update')
        @if (in_array($submission->status, ['draft', 'in_approval', 'submitted'], true))
            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label fw-bold">Catatan Revisi untuk Dosen</label>
                    <textarea class="form-control" rows="3" wire:model.defer="revisionNotes" placeholder="Tuliskan bagian yang harus diperbaiki dosen..."></textarea>
                    <small class="text-muted d-block mt-2">Catatan ini akan tampil di halaman BKD dosen saat status berubah menjadi revisi.</small>
                </div>
            </div>
        @endif
    @endactivecan
    @if ($submission->review_notes)
        <div class="alert alert-warning">
            <div class="fw-bold mb-1">Catatan Review</div>
            <div>{{ $submission->review_notes }}</div>
        </div>
    @endif
    <div class="row mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Mengajar</small><h2 class="mb-0">{{ $submission->teaching_sks }}</h2></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Jabatan</small><h2 class="mb-0">{{ $submission->structural_sks }}</h2></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Tridharma</small><h2 class="mb-0">{{ $submission->tridharma_sks }}</h2></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Total</small><h2 class="text-primary mb-0">{{ $submission->total_sks }}</h2></div></div></div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table card-table">
                <thead><tr><th>Kategori</th><th>Aktivitas</th><th>Kode</th><th>SKS</th></tr></thead>
                <tbody>
                    @foreach ($submission->items as $item)
                        <tr><td>{{ str($item->category)->title() }}</td><td>{{ $item->title }}</td><td>{{ $item->source_code }}</td><td class="fw-bold">{{ $item->sks }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
