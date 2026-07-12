<?php

use App\Models\Academic\StudyPlan;
use Livewire\Component;

new class extends Component {
    public StudyPlan $studyPlan;

    public function mount($id): void
    {
        $this->studyPlan = StudyPlan::with([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'studentRegistration',
            'approvedBy',
            'details.courseOffering.course',
        ])->findOrFail($id);
    }

    public function backToIndex(): void
    {
        $this->redirectRoute('admin.academic.study-plans.index');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Detail KRS',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Detail Kartu Rencana Studi (KRS)"
        description="Informasi rancangan studi mahasiswa, daftar mata kuliah yang diambil, serta status persetujuan (approval)."
        icon="book-open"
    >
        <div class="d-flex align-items-center gap-2">
            @activecan('study-plan.update')
                <a href="{{ route('admin.academic.study-plans.edit', ['id' => $studyPlan->id]) }}" class="btn btn-sm btn-light text-warning fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border">
                    <i class="fa fa-edit"></i> <span>Edit KRS</span>
                </a>
            @endactivecan
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border" wire:click="backToIndex">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </button>
        </div>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-id-card fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Info Header KRS</h4>
                            <div class="text-muted small">Mahasiswa, program studi, semester, dan status persetujuan saat ini.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Nama Mahasiswa</label>
                            <div class="fw-bold text-dark fs-6">{{ $studyPlan->studentProfile?->user?->name ?? '-' }}</div>
                            <div class="small text-muted">NIM: {{ $studyPlan->studentProfile?->nim ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Program Studi</label>
                            <div class="fw-semibold text-dark">{{ $studyPlan->studentProfile?->studyProgram?->name ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Tahun Akademik</label>
                            <div class="fw-bold text-primary fs-6">{{ $studyPlan->academicYear?->name ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Semester Ke</label>
                            <div class="fw-semibold text-dark">{{ $studyPlan->semester_no ? 'Semester ' . $studyPlan->semester_no : '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Status KRS</label>
                            <div>
                                @php
                                    $statusBadge = match($studyPlan->status) {
                                        'Draft' => 'bg-secondary',
                                        'Submitted' => 'bg-warning text-dark',
                                        'Approved' => 'bg-success',
                                        'Rejected' => 'bg-danger',
                                        'Cancelled' => 'bg-dark',
                                        default => 'bg-info'
                                    };
                                @endphp
                                <span class="badge rounded-pill px-3 py-2 {{ $statusBadge }}">{{ $studyPlan->status }}</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Student Registration Terkait</label>
                            <div class="fw-semibold text-dark">{{ $studyPlan->student_registration_id ? '#'.$studyPlan->student_registration_id : '-' }}</div>
                        </div>

                        @if ($studyPlan->notes)
                            <div class="col-12 mt-3">
                                <label class="text-muted small d-block mb-1">Catatan Tambahan</label>
                                <div class="p-3 bg-light rounded-3 text-dark border">{{ $studyPlan->notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-list-check fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Daftar Mata Kuliah (KRS)</h4>
                            <div class="text-muted small">Rincian mata kuliah yang diambil dalam rencana studi semester ini.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @if ($studyPlan->details->count() > 0)
                        @php
                            $sortedDetails = $studyPlan->details->sortBy(fn($detail) => [
                                $detail->courseOffering?->semester_no ?? 999,
                                $detail->courseOffering?->course?->name ?? ''
                            ]);
                            $totalSks = (int) $studyPlan->details->sum('credits');
                        @endphp

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3 px-3" style="width: 50px;">No</th>
                                        <th class="py-3 px-3">Mata Kuliah & Kelas</th>
                                        <th class="py-3 px-3 text-center">Semester</th>
                                        <th class="py-3 px-3 text-center">SKS</th>
                                        <th class="py-3 px-3 text-center">Status</th>
                                        <th class="py-3 px-3 text-center">Mengulang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sortedDetails as $index => $detail)
                                        <tr>
                                            <td class="px-3 fw-semibold text-muted">{{ $index + 1 }}</td>
                                            <td class="px-3">
                                                <div class="fw-bold text-dark">
                                                    {{ $detail->courseOffering?->course?->code ?? '-' }} - {{ $detail->courseOffering?->course?->name ?? '-' }}
                                                </div>
                                                <small class="text-muted"><i class="fa fa-tag me-1"></i>Kelas: {{ $detail->courseOffering?->label ?? '-' }}</small>
                                            </td>
                                            <td class="px-3 text-center">{{ $detail->courseOffering?->semester_no ?? '-' }}</td>
                                            <td class="px-3 text-center fw-bold text-primary">{{ $detail->credits ?? '-' }}</td>
                                            <td class="px-3 text-center">
                                                <span class="badge rounded-pill px-3 py-2 @if($detail->status === 'Taken') bg-success @elseif($detail->status === 'Dropped' || $detail->status === 'Cancelled') bg-danger @else bg-info @endif">
                                                    {{ $detail->status }}
                                                </span>
                                            </td>
                                            <td class="px-3 text-center">
                                                @if ($detail->is_repeat)
                                                    <span class="badge bg-warning text-dark rounded-pill px-2">Ya</span>
                                                @else
                                                    <span class="badge bg-light text-muted border rounded-pill px-2">Tidak</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="3" class="text-end py-3 px-3 fw-bold">Total Pengambilan:</th>
                                        <th class="text-center py-3 px-3 fw-bold text-primary fs-6">{{ $totalSks }} SKS</th>
                                        <th colspan="2" class="py-3 px-3 fw-semibold text-muted">{{ $studyPlan->details->count() }} Mata Kuliah</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info border-0 shadow-sm rounded-4 p-4 mb-0 d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                                <i class="fa fa-info-circle fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Belum Ada Mata Kuliah Diambil</h6>
                                <div class="small">Rencana studi ini belum memiliki rincian mata kuliah yang didaftarkan.</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-history fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1 text-dark">Status & Persetujuan</h5>
                            <div class="text-muted small">Riwayat submit dan approval.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Status KRS Saat Ini</label>
                        <span class="badge rounded-pill px-3 py-2 {{ $statusBadge }}">{{ $studyPlan->status }}</span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Waktu Pengajuan (Submitted)</label>
                        <div class="fw-semibold text-dark small">{{ $studyPlan->submitted_at ? $studyPlan->submitted_at->format('d F Y H:i') : '-' }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Waktu Persetujuan (Approved)</label>
                        <div class="fw-semibold text-dark small">{{ $studyPlan->approved_at ? $studyPlan->approved_at->format('d F Y H:i') : '-' }}</div>
                    </div>

                    <div>
                        <label class="text-muted small d-block mb-1">Disetujui Oleh</label>
                        <div class="fw-semibold text-dark small">{{ $studyPlan->approvedBy?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
