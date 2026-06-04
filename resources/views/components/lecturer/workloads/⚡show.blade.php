<?php

use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\Organization\LecturerWorkloadService;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public LecturerWorkloadSubmission $submission;
    public string $notes = '';

    public function mount($id): void
    {
        $this->submission = LecturerWorkloadSubmission::with(['period', 'items', 'approvalRequest.steps'])->where('user_id', auth()->id())->findOrFail($id);
        $this->notes = $this->submission->lecturer_notes ?: '';
    }

    public function submit(): void
    {
        $this->submission = app(LecturerWorkloadService::class)->submit($this->submission, auth()->user(), $this->notes);
        session()->flash('success', 'BKD berhasil diajukan.');
    }

    public function regenerate(): void
    {
        $this->submission = app(LecturerWorkloadService::class)->generateFor(auth()->user(), $this->submission->period);
        $this->notes = $this->submission->lecturer_notes ?: $this->notes;
        session()->flash('success', 'Draf BKD berhasil disiapkan ulang dari data terbaru.');
    }

    public function statusLabel(): string
    {
        return match ($this->submission->status) {
            'in_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => 'Draf',
        };
    }

    public function categoryLabel(string $category): string
    {
        return match ($category) {
            'teaching' => 'Mengajar',
            'structural' => 'Jabatan',
            'tridharma' => 'Tridharma',
            default => str($category)->replace('_', ' ')->title()->toString(),
        };
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Detail BKD']);
    }
};
?>

@include('components.lecturer.assignments.assignment-styles')

<div>
    <x-alert />

    <div class="card assignment-card assignment-hero mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="d-flex justify-content-between gap-3 flex-wrap">
                <div class="d-flex gap-3">
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-scale-balanced"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Detail Beban Kerja</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">{{ $submission->period?->name }}</h1>
                        <div style="opacity:.9;">Status {{ $this->statusLabel() }} / Total {{ $submission->total_sks }} SKS</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-chalkboard-teacher"></i>Mengajar {{ $submission->teaching_sks }}</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-sitemap"></i>Jabatan {{ $submission->structural_sks }}</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-book-open"></i>Tridharma {{ $submission->tridharma_sks }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('lecturer.workloads.index') }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#4f46e5;"><i class="fas fa-arrow-left"></i>Kembali</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Mengajar</div><div class="h2 fw-bold mb-0">{{ $submission->teaching_sks }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Jabatan</div><div class="h2 fw-bold mb-0">{{ $submission->structural_sks }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Tridharma</div><div class="h2 fw-bold mb-0">{{ $submission->tridharma_sks }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Total</div><div class="h2 fw-bold text-primary mb-0">{{ $submission->total_sks }}</div></div></div>
    </div>

    @if (in_array($submission->status, ['draft', 'revision', 'rejected', 'cancelled'], true))
        <div class="card assignment-card mb-4">
            <div class="card-header py-3">
                <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-paper-plane me-2 text-primary"></i>{{ $submission->status === 'revision' ? 'Perbaiki & Ajukan Ulang' : 'Ajukan Review' }}</h3>
            </div>
            <div class="card-body p-4">
                @if ($submission->status === 'revision')
                    <div class="assignment-panel mb-3" style="background:#fffbeb;border-color:#fde68a;">
                        <div class="fw-bold text-warning"><i class="fas fa-rotate-left me-2"></i>BKD perlu revisi</div>
                        <div class="text-secondary mt-1">{{ $submission->review_notes ?: 'Reviewer meminta perbaikan BKD. Periksa sumber data, siapkan ulang draf bila perlu, lalu ajukan ulang.' }}</div>
                    </div>
                @endif
                <div class="assignment-panel mb-3">
                    <label class="form-label fw-bold">Catatan Pengajuan</label>
                    <textarea class="form-control" rows="3" wire:model.defer="notes" placeholder="{{ $submission->status === 'revision' ? 'Jelaskan perbaikan yang sudah dilakukan...' : 'Tambahkan catatan untuk reviewer...' }}"></textarea>
                </div>
                <div class="d-flex justify-content-between gap-2 flex-wrap">
                    <button type="button" class="assignment-action" wire:click="regenerate" wire:loading.attr="disabled" style="background:#f8fafc;color:#475569;border:1px solid #e2e8f0;">
                        <span wire:loading.remove wire:target="regenerate"><i class="fas fa-wand-magic-sparkles"></i>Siapkan Ulang Draf</span>
                        <span wire:loading wire:target="regenerate"><i class="fas fa-spinner fa-spin"></i>Memproses...</span>
                    </button>
                    <button type="button" class="assignment-action" wire:click="submit" wire:loading.attr="disabled" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                        <span wire:loading.remove wire:target="submit"><i class="fas fa-paper-plane"></i>{{ $submission->status === 'revision' ? 'Ajukan Ulang BKD' : 'Ajukan BKD' }}</span>
                        <span wire:loading wire:target="submit"><i class="fas fa-spinner fa-spin"></i>Memproses...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Breakdown Aktivitas</h3>
            <span class="assignment-pill">{{ $submission->items->count() }} item</span>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($submission->items as $item)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-8">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-clipboard-list"></i></span>
                                    <div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill">{{ $this->categoryLabel($item->category) }}</span>
                                            @if ($item->source_code)
                                                <span class="assignment-pill"><i class="fas fa-hashtag"></i>{{ $item->source_code }}</span>
                                            @endif
                                        </div>
                                        <div class="fw-bold">{{ $item->title }}</div>
                                        @if (data_get($item->snapshot, 'description'))
                                            <div class="text-secondary small mt-1">{{ data_get($item->snapshot, 'description') }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4 text-xl-end">
                                <div class="assignment-panel d-inline-block text-start" style="min-width:160px;">
                                    <div class="text-secondary small">Konversi SKS</div>
                                    <div class="h3 fw-bold text-primary mb-0">{{ $item->sks }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <div>Belum ada aktivitas dalam BKD ini.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
