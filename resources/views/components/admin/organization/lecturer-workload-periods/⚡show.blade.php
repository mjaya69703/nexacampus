<?php

use App\Models\Organization\LecturerWorkloadPeriod;
use Livewire\Component;

new class extends Component
{
    public LecturerWorkloadPeriod $period;

    public function mount($id): void
    {
        $this->period = LecturerWorkloadPeriod::with(['submissions.owner', 'submissions.items'])->findOrFail($id);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Detail Periode BKD']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Periode BKD: {{ $period->name }}"
        description="Detail informasi siklus laporan Beban Kerja Dosen &bull; Kode: {{ $period->code }} &bull; Rentang Waktu: {{ $period->starts_at?->format('d M Y') ?? '-' }} s.d. {{ $period->ends_at?->format('d M Y') ?? '-' }}"
        icon="calendar-check"
    >
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('admin.organization.lecturer-workload-periods.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
            @activecan('lecturer-workload-period.update')
                <a href="{{ route('admin.organization.lecturer-workload-periods.edit', $period->id) }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-edit"></i> <span>Edit Periode</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Periode</div>
                        <div class="fw-bold">{{ strtoupper($period->status) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-file-lines fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Laporan Masuk</div>
                        <div class="fw-bold">{{ number_format($period->submissions->count()) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Disetujui (Approved)</div>
                        <div class="fw-bold">{{ number_format($period->submissions->where('status', 'approved')->count()) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calculator fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Rata-Rata SKS</div>
                        <div class="fw-bold">{{ number_format($period->submissions->avg('total_sks') ?: 0, 1) }} SKS</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-file-lines fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Daftar Laporan BKD Masuk (Submission)</h4>
                    <span class="text-muted small">Rincian realisasi SKS pendidikan/pengajaran, tugas struktural, dan tri dharma lainnya untuk periode ini.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0 pt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-secondary text-uppercase small">Nama Dosen Pemohon</th>
                            <th class="py-3 text-secondary text-uppercase small">Status Laporan</th>
                            <th class="py-3 text-center text-secondary text-uppercase small">Mengajar</th>
                            <th class="py-3 text-center text-secondary text-uppercase small">Struktural</th>
                            <th class="py-3 text-center text-secondary text-uppercase small">Tridharma Lainnya</th>
                            <th class="py-3 text-center text-secondary text-uppercase small">Total SKS</th>
                            <th class="pe-4 py-3 text-end text-secondary text-uppercase small">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($period->submissions as $submission)
                            <tr class="border-bottom">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                            {{ strtoupper(substr($submission->owner?->name ?? 'D', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ $submission->owner?->name ?? '-' }}</div>
                                            <span class="small text-muted">{{ $submission->owner?->email ?? '' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    @php
                                        $statusClass = match ($submission->status) {
                                            'approved' => 'bg-success text-white',
                                            'rejected' => 'bg-danger text-white',
                                            'in_approval', 'submitted' => 'bg-warning text-dark',
                                            default => 'bg-secondary text-white',
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }} rounded-pill px-3 py-1">{{ str($submission->status)->replace('_', ' ')->title() }}</span>
                                </td>
                                <td class="py-3 text-center text-dark">{{ number_format((float) $submission->teaching_sks, 1) }} SKS</td>
                                <td class="py-3 text-center text-dark">{{ number_format((float) $submission->structural_sks, 1) }} SKS</td>
                                <td class="py-3 text-center text-dark">{{ number_format((float) $submission->tridharma_sks, 1) }} SKS</td>
                                <td class="py-3 text-center">
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fs-6 fw-bold">
                                        {{ number_format((float) $submission->total_sks, 1) }} SKS
                                    </span>
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <a class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-medium" href="{{ route('admin.organization.lecturer-workload-submissions.show', $submission->id) }}">
                                        <i class="fas fa-eye me-1"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-folder-open fs-3 d-block mb-2 text-secondary"></i>
                                    Belum ada laporan BKD masuk dari dosen untuk periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
