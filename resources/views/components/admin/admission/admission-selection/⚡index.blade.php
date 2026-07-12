<?php

use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionSelectionService;
use App\Support\Admission\AdmissionStatusService;
use Livewire\Component;

new class extends Component
{
    public string $periodId = '';
    public string $studyProgramId = '';
    public string $classType = '';
    public string $bulkStatus = 'accepted';
    public array $selectedIds = [];
    public array $periods = [];
    public array $studyPrograms = [];

    public function mount(): void
    {
        $this->periods = AdmissionPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code'])
            ->map(fn ($period) => ['id' => $period->id, 'label' => $period->name.' ('.$period->code.')'])
            ->toArray();

        $this->studyPrograms = StudyProgram::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($program) => ['id' => $program->id, 'label' => $program->name.' ('.$program->code.')'])
            ->toArray();
    }

    public function rows(AdmissionSelectionService $selectionService)
    {
        return $selectionService->rankedRows($this->filters());
    }

    public function updateDecision(int $applicationId, string $status, AdmissionStatusService $statusService, AdmissionSelectionService $selectionService): void
    {
        if (! ActivePermission::check('admission-selection.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah keputusan seleksi.');
            return;
        }

        if (! in_array($status, ['accepted', 'waitlisted', 'rejected', 'under_review'], true)) {
            session()->flash('error', 'Status keputusan tidak valid.');
            return;
        }

        $application = AdmissionApplication::findOrFail($applicationId);
        $quota = $selectionService->quotaFor($application);

        if ($status === 'accepted' && $quota && $quota->accepted_count >= $quota->quota && $application->status !== 'accepted') {
            session()->flash('error', 'Kuota untuk program studi & kelas ini sudah penuh. Gunakan status cadangan (waitlist) atau tambah kuota.');
            return;
        }

        $statusService->change($application, $status, 'Selection decision updated from ranking dashboard.', auth()->id());
        $selectionService->refreshAcceptedCounts($application->admission_period_id);

        session()->flash('success', 'Keputusan seleksi pendaftar berhasil diperbarui.');
    }

    public function applyBulkDecision(AdmissionStatusService $statusService, AdmissionSelectionService $selectionService): void
    {
        if (! ActivePermission::check('admission-selection.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah keputusan seleksi massal.');
            return;
        }

        $ids = collect($this->selectedIds)->filter()->values();

        if ($ids->isEmpty()) {
            session()->flash('error', 'Pilih minimal satu pendaftar terlebih dahulu.');
            return;
        }

        if (! in_array($this->bulkStatus, ['accepted', 'waitlisted', 'rejected', 'under_review'], true)) {
            session()->flash('error', 'Status bulk tidak valid.');
            return;
        }

        $updated = 0;
        $errors = [];

        foreach (AdmissionApplication::whereKey($ids)->get() as $application) {
            $quota = $selectionService->quotaFor($application);

            if ($this->bulkStatus === 'accepted' && $quota && $quota->accepted_count >= $quota->quota && $application->status !== 'accepted') {
                $errors[] = $application->application_number.' dilewati: kuota penuh.';
                continue;
            }

            $statusService->change($application, $this->bulkStatus, 'Bulk selection decision updated.', auth()->id());
            $selectionService->refreshAcceptedCounts($application->admission_period_id);
            $updated++;
        }

        $this->selectedIds = [];

        if ($updated > 0) {
            session()->flash('success', $updated.' pendaftar berhasil diperbarui status seleksinya.');
        }

        if ($errors) {
            session()->flash('error', implode(' ', $errors));
        }
    }

    public function render()
    {
        $totalCandidates = AdmissionApplication::where('status', '!=', 'submitted')->count();
        $acceptedCount = AdmissionApplication::where('status', 'accepted')->count();
        $waitlistedCount = AdmissionApplication::where('status', 'waitlisted')->count();
        $rejectedCount = AdmissionApplication::where('status', 'rejected')->count();

        return $this->view([
            'totalCandidates' => $totalCandidates,
            'acceptedCount' => $acceptedCount,
            'waitlistedCount' => $waitlistedCount,
            'rejectedCount' => $rejectedCount,
        ])->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Perankingan & Seleksi Calon Mahasiswa',
        ]);
    }

    private function filters(): array
    {
        return [
            'period_id' => $this->periodId ?: null,
            'study_program_id' => $this->studyProgramId ?: null,
            'class_type' => $this->classType ?: null,
        ];
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Perankingan & Seleksi Calon Mahasiswa"
        description="Evaluasi skor akhir ujian, pantau keterisian kuota daya tampung program studi, dan tetapkan keputusan kelulusan calon mahasiswa secara individual atau massal (Bulk Decision)."
        icon="award"
    >
        <x-slot:stats>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-users-cog text-warning fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Dinilai & Seleksi</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalCandidates) }} <small class="fs-7 fw-normal">Peserta</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-check-circle text-success fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Lulus (Diterima)</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($acceptedCount) }} <small class="fs-7 fw-normal">Peserta</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-clock text-info fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Cadangan (Waitlist)</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($waitlistedCount) }} <small class="fs-7 fw-normal">Peserta</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-times-circle text-danger fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Tidak Lulus</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($rejectedCount) }} <small class="fs-7 fw-normal">Peserta</small></div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="fas fa-filter text-primary"></i> Filter Peringkat Seleksi
            </h4>
            <p class="text-muted fs-7 mb-0">Pilih gelombang admission, program studi, dan tipe kelas untuk menampilkan peringkat nilai tertinggi ke terendah.</p>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-dark fs-7">Gelombang / Periode</label>
                    <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model.live="periodId">
                        <option value="">-- Semua Periode --</option>
                        @foreach($periods as $period)
                            <option value="{{ $period['id'] }}">{{ $period['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-dark fs-7">Program Studi</label>
                    <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model.live="studyProgramId">
                        <option value="">-- Semua Program Studi --</option>
                        @foreach($studyPrograms as $program)
                            <option value="{{ $program['id'] }}">{{ $program['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-dark fs-7">Tipe Kelas</label>
                    <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model.live="classType">
                        <option value="">-- Semua Tipe Kelas --</option>
                        <option value="regular">Reguler Pagi</option>
                        <option value="evening">Kelas Malam</option>
                        <option value="weekend">Kelas Akhir Pekan (Weekend)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    @activecan('admission-selection.update')
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary bg-opacity-10 border-start border-primary border-4">
            <div class="card-body p-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-primary fs-7">Ubah Status Massal (Bulk Decision)</label>
                        <select class="form-select rounded-3 shadow-sm border-0" wire:model="bulkStatus">
                            <option value="accepted">Terima (Accepted)</option>
                            <option value="waitlisted">Cadangan (Waitlisted)</option>
                            <option value="rejected">Tolak (Rejected)</option>
                            <option value="under_review">Kembalikan ke Seleksi (Under Review)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary rounded-pill fw-bold px-4 py-2 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2" wire:click="applyBulkDecision">
                            <i class="fas fa-check-double"></i> Terapkan ke Terpilih
                        </button>
                    </div>
                    <div class="col-md-5 text-dark fw-medium d-flex align-items-center">
                        <span class="badge bg-primary rounded-pill px-3 py-2 fs-7 shadow-sm me-2">
                            {{ count(array_filter($selectedIds)) }}
                        </span>
                        peserta dipilih untuk aksi massal.
                    </div>
                </div>
            </div>
        </div>
    @endactivecan

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-list-ol text-primary"></i> Tabel Peringkat Nilai Calon Mahasiswa
                </h4>
                <p class="text-muted fs-7 mb-0">Urutan peringkat dihitung dari akumulasi skor seleksi. Anda dapat menetapkan keputusan langsung pada tiap baris.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th style="width:40px;" class="ps-4"><input type="checkbox" class="form-check-input"></th>
                        <th class="fw-bold text-muted fs-7">PERINGKAT</th>
                        <th class="fw-bold text-muted fs-7">PENDAFTAR</th>
                        <th class="fw-bold text-muted fs-7">PROGRAM STUDI & KELAS</th>
                        <th class="fw-bold text-muted fs-7 text-center">SKOR AKHIR</th>
                        <th class="fw-bold text-muted fs-7 text-center">NILAI UJIAN</th>
                        <th class="fw-bold text-muted fs-7 text-center">KUOTA</th>
                        <th class="fw-bold text-muted fs-7 text-center">STATUS SELEKSI</th>
                        <th class="fw-bold text-muted fs-7 text-end pe-4">KEPUTUSAN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->rows(app(App\Support\Admission\AdmissionSelectionService::class)) as $row)
                        <tr>
                            <td class="ps-4"><input type="checkbox" class="form-check-input" value="{{ $row->id }}" wire:model="selectedIds"></td>
                            <td>
                                <span class="badge bg-dark rounded-pill px-3 py-1 fs-7 shadow-sm">
                                    #{{ $row->rank_position }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-6">{{ $row->full_name }}</div>
                                <span class="text-muted fs-7">{{ $row->application_number }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $row->studyProgram?->name ?? '-' }}</div>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-2 py-0.5 fs-8">
                                    {{ ucfirst($row->class_type ?? '-') }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold text-primary fs-6">{{ $row->final_score ?? '-' }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-2.5 py-1">
                                    {{ $row->scores_count }} komponen
                                </span>
                            </td>
                            <td class="text-center">
                                @if($row->quota_limit)
                                    @php
                                        $isFull = $row->quota_used >= $row->quota_limit;
                                    @endphp
                                    <span class="badge {{ $isFull ? 'bg-danger text-white' : 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' }} rounded-pill px-2.5 py-1">
                                        {{ $row->quota_used }}/{{ $row->quota_limit }} terisi
                                    </span>
                                @else
                                    <span class="text-muted fs-7">Tanpa Batas</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    $statusBadgeClass = match($row->status) {
                                        'accepted' => 'bg-success bg-opacity-10 text-success border-success',
                                        'rejected' => 'bg-danger bg-opacity-10 text-danger border-danger',
                                        'waitlisted' => 'bg-warning bg-opacity-10 text-warning border-warning',
                                        'under_review' => 'bg-info bg-opacity-10 text-info border-info',
                                        default => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $statusBadgeClass }} border border-opacity-25 rounded-pill px-3 py-1 fs-7">
                                    {{ str($row->status)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                @activecan('admission-selection.update')
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-outline-success rounded-pill px-2.5 py-1 text-success fw-medium shadow-sm d-inline-flex align-items-center gap-1" wire:click="updateDecision({{ $row->id }}, 'accepted')" title="Terima">
                                            <i class="fas fa-check"></i> Terima
                                        </button>
                                        <button class="btn btn-outline-warning rounded-pill px-2.5 py-1 text-warning fw-medium shadow-sm d-inline-flex align-items-center gap-1" wire:click="updateDecision({{ $row->id }}, 'waitlisted')" title="Cadangan">
                                            <i class="fas fa-clock"></i> Cadangan
                                        </button>
                                        <button class="btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1" wire:click="updateDecision({{ $row->id }}, 'rejected')" title="Tolak">
                                            <i class="fas fa-times"></i> Tolak
                                        </button>
                                    </div>
                                @endactivecan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <div class="py-4">
                                    <i class="fas fa-user-slash fs-1 text-secondary opacity-50 mb-3"></i>
                                    <p class="fs-6 fw-medium mb-1">Belum ada pendaftar untuk filter yang dipilih.</p>
                                    <small class="text-muted">Silakan pilih gelombang atau program studi lain pada filter di atas.</small>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
