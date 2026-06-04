<?php

use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\Organization\LecturerWorkloadService;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public array $periods = [];
    public array $submissions = [];
    public string $selectedPeriodId = '';
    public string $notes = '';

    public function mount(): void
    {
        $this->loadData();
    }

    public function generate(): void
    {
        $period = LecturerWorkloadPeriod::findOrFail($this->selectedPeriodId);
        $submission = app(LecturerWorkloadService::class)->generateFor(auth()->user(), $period);
        session()->flash('success', 'Draf BKD berhasil disiapkan.');
        $this->redirectRoute('lecturer.workloads.show', ['id' => $submission->id]);
    }

    private function loadData(): void
    {
        $this->periods = LecturerWorkloadPeriod::query()
            ->whereIn('status', ['open', 'review'])
            ->latest('starts_at')
            ->get()
            ->map(fn ($period) => ['id' => $period->id, 'name' => $period->name, 'status' => $period->status])
            ->all();

        $this->selectedPeriodId = $this->selectedPeriodId ?: (string) (collect($this->periods)->first()['id'] ?? '');

        $this->submissions = LecturerWorkloadSubmission::query()
            ->where('user_id', auth()->id())
            ->with('period')
            ->latest('updated_at')
            ->get()
            ->map(fn ($submission) => [
                'id' => $submission->id,
                'period' => $submission->period?->name ?? '-',
                'status' => $submission->status,
                'teaching_sks' => $submission->teaching_sks,
                'structural_sks' => $submission->structural_sks,
                'tridharma_sks' => $submission->tridharma_sks,
                'total_sks' => $submission->total_sks,
                'updated_at' => $submission->updated_at?->format('d M Y H:i'),
            ])
            ->all();
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'in_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => 'Draf',
        };
    }

    public function statusStyle(string $status): string
    {
        return match ($status) {
            'in_approval' => 'background:#fef3c7;color:#b45309;',
            'approved' => 'background:#dcfce7;color:#15803d;',
            'revision' => 'background:#ffedd5;color:#ea580c;',
            'rejected' => 'background:#fee2e2;color:#dc2626;',
            'cancelled' => 'background:#f1f5f9;color:#64748b;',
            default => 'background:#eef2ff;color:#4338ca;',
        };
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'BKD Saya']);
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.8rem;"><i class="fas fa-scale-balanced"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Kepegawaian Dosen</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">BKD Saya</h1>
                        <div style="opacity:.9;">Siapkan beban kerja dari mengajar, jabatan, dan Tridharma yang sudah tercatat.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-file-signature"></i>{{ count($submissions) }} pengajuan</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-clock"></i>{{ collect($submissions)->where('status', 'in_approval')->count() }} approval</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-check-circle"></i>{{ collect($submissions)->where('status', 'approved')->count() }} disetujui</span>
                        </div>
                    </div>
                </div>
                <div style="min-width:min(100%, 360px);">
                    <label class="form-label text-white fw-bold">Periode BKD</label>
                    <div class="d-flex gap-2">
                        <select class="form-control" wire:model.defer="selectedPeriodId">
                            @foreach ($periods as $period)
                                <option value="{{ $period['id'] }}">{{ $period['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-light" wire:click="generate" @disabled($selectedPeriodId === '')><i class="fas fa-wand-magic-sparkles me-1"></i>Siapkan Draf</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Submission</div><div class="h2 fw-bold mb-0">{{ count($submissions) }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Approval</div><div class="h2 fw-bold mb-0 text-warning">{{ collect($submissions)->where('status', 'in_approval')->count() }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Disetujui</div><div class="h2 fw-bold mb-0 text-success">{{ collect($submissions)->where('status', 'approved')->count() }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">SKS Terakhir</div><div class="h2 fw-bold mb-0 text-primary">{{ collect($submissions)->first()['total_sks'] ?? '0.00' }}</div></div></div>
    </div>

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Riwayat BKD</h3>
            <span class="assignment-pill">{{ count($submissions) }} pengajuan</span>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($submissions as $submission)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-6">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-scale-balanced"></i></span>
                                    <div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill" style="{{ $this->statusStyle($submission['status']) }}">{{ $this->statusLabel($submission['status']) }}</span>
                                            <span class="assignment-pill"><i class="fas fa-clock"></i>Update {{ $submission['updated_at'] }}</span>
                                        </div>
                                        <div class="fw-bold">{{ $submission['period'] }}</div>
                                        <div class="text-secondary small">Breakdown SKS tersimpan untuk periode ini.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="assignment-panel">
                                    <div class="d-flex justify-content-between border-bottom py-1"><span>Mengajar</span><strong>{{ $submission['teaching_sks'] }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span>Jabatan</span><strong>{{ $submission['structural_sks'] }}</strong></div>
                                    <div class="d-flex justify-content-between pt-1"><span>Tridharma</span><strong>{{ $submission['tridharma_sks'] }}</strong></div>
                                </div>
                            </div>
                            <div class="col-xl-2 text-xl-end">
                                <div class="h3 mb-2">{{ $submission['total_sks'] }} SKS</div>
                                <a href="{{ route('lecturer.workloads.show', $submission['id']) }}" class="assignment-action" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;"><i class="fas fa-eye"></i>Lihat</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <div>Belum ada BKD. Pilih periode lalu siapkan draf.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
