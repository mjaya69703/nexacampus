<?php

use Livewire\Component;
use App\Models\Academic\StudentRegistration;

new class extends Component {
    public $registration;

    public function mount($id): void
    {
        $this->registration = StudentRegistration::with([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'approvedBy',
        ])->findOrFail($id);
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Detail Registrasi Mahasiswa',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Detail Registrasi Mahasiswa"
        description="Informasi lengkap biodata mahasiswa, status akademik semester, serta timeline persetujuan (approval)."
        icon="user-check"
    >
        <div class="d-flex align-items-center gap-2">
            @activecan('student-registration.update')
                <a href="{{ route('admin.academic.student-registrations.edit', ['id' => $registration->id]) }}" class="btn btn-sm btn-light text-warning fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border">
                    <i class="fa fa-edit"></i> <span>Edit Registrasi</span>
                </a>
            @endactivecan
            <a href="{{ route('admin.academic.student-registrations.index') }}" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Data Mahasiswa</h4>
                            <div class="text-muted small">Biodata dan identitas akademik mahasiswa terdaftar.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Nama Mahasiswa</label>
                            <div class="fw-bold text-dark fs-6">{{ $registration->studentProfile?->user?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Nomor Induk Mahasiswa (NIM)</label>
                            <div class="fw-semibold text-dark">{{ $registration->studentProfile?->nim ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Program Studi</label>
                            <div class="fw-semibold text-dark">{{ $registration->studentProfile?->studyProgram?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Alamat Email</label>
                            <div class="fw-semibold text-dark">{{ $registration->studentProfile?->user?->email ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-file-invoice fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Detail Parameter Registrasi</h4>
                            <div class="text-muted small">Tahun akademik, semester, dan status registrasi saat ini.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Tahun Akademik</label>
                            <div class="fw-bold text-primary fs-6">{{ $registration->academicYear?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Semester Ke</label>
                            <div class="fw-semibold text-dark">{{ $registration->semester_no ? 'Semester ' . $registration->semester_no : '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Status Registrasi</label>
                            <div>
                                <span class="badge rounded-pill px-3 py-2 @if($registration->registration_status === 'Approved') bg-success @elseif($registration->registration_status === 'Rejected') bg-danger @elseif($registration->registration_status === 'Submitted') bg-warning text-dark @elseif($registration->registration_status === 'Cancelled') bg-secondary @else bg-info @endif">
                                    {{ $registration->registration_status }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Status Akademik</label>
                            <div class="fw-semibold text-dark">{{ $registration->academic_status }}</div>
                        </div>
                        @if($registration->notes)
                            <div class="col-12 mt-3">
                                <label class="text-muted small d-block mb-1">Catatan Tambahan</label>
                                <div class="p-3 bg-light rounded-3 text-dark border">{{ $registration->notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($registration->registration_status === 'Approved' || $registration->registration_status === 'Rejected' || $registration->submitted_at)
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-history fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Timeline Approval</h4>
                                <div class="text-muted small">Jejak pengajuan dan proses persetujuan.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="timeline ps-3">
                            @if($registration->submitted_at)
                                <div class="timeline-item pb-4">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content ms-3">
                                        <div class="text-muted small">Diajukan pada</div>
                                        <div class="fw-semibold text-dark">{{ $registration->submitted_at->format('d F Y H:i') }}</div>
                                    </div>
                                </div>
                            @endif

                            @if($registration->approved_at)
                                <div class="timeline-item">
                                    <div class="timeline-marker @if($registration->registration_status === 'Approved') bg-success @else bg-danger @endif"></div>
                                    <div class="timeline-content ms-3">
                                        <div class="text-muted small">Diproses pada</div>
                                        <div class="fw-semibold text-dark">{{ $registration->approved_at->format('d F Y H:i') }}</div>
                                        <div class="text-muted small mt-1">Oleh: <span class="fw-semibold text-dark">{{ $registration->approvedBy?->name ?? '-' }}</span></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-info-circle fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1 text-dark">Status Ringkas</h5>
                            <div class="text-muted small">Ringkasan cepat kondisi registrasi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Status Registrasi</label>
                        <span class="badge rounded-pill px-3 py-2 @if($registration->registration_status === 'Approved') bg-success @elseif($registration->registration_status === 'Rejected') bg-danger @elseif($registration->registration_status === 'Submitted') bg-warning text-dark @elseif($registration->registration_status === 'Cancelled') bg-secondary @else bg-info @endif">
                            {{ $registration->registration_status }}
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Status Akademik</label>
                        <span class="badge rounded-pill bg-light text-dark border px-3 py-2">{{ $registration->academic_status }}</span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Keaktifan Registrasi</label>
                        <span class="badge rounded-pill px-3 py-2 @if($registration->is_active) bg-success @else bg-danger @endif">
                            @if($registration->is_active) <i class="fa fa-check me-1"></i> Aktif @else <i class="fa fa-times me-1"></i> Nonaktif @endif
                        </span>
                    </div>

                    @if($registration->registration_status !== 'Approved' && $registration->registration_status !== 'Rejected')
                        <div class="alert alert-warning border-0 rounded-3 mb-0 mt-3 d-flex align-items-center gap-2">
                            <i class="fa fa-clock text-warning"></i>
                            <div class="small fw-semibold">Menunggu Proses Approval</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-clock fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1 text-dark">Metadata Sistem</h5>
                            <div class="text-muted small">Riwayat rekam data.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Dibuat pada</label>
                        <div class="fw-semibold text-dark small">{{ $registration->created_at?->format('d F Y H:i') ?? '-' }}</div>
                    </div>
                    <div>
                        <label class="text-muted small d-block mb-1">Diperbarui pada</label>
                        <div class="fw-semibold text-dark small">{{ $registration->updated_at?->format('d F Y H:i') ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .timeline {
        position: relative;
    }

    .timeline-item {
        position: relative;
    }

    .timeline-item:not(:last-child)::before {
        content: '';
        position: absolute;
        left: 7px;
        top: 24px;
        bottom: -10px;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-marker {
        position: absolute;
        left: 0;
        top: 4px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 0 0 2px #e9ecef;
    }
</style>
