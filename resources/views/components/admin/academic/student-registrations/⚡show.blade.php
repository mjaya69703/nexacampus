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

<div class="row">
    <div class="col-lg-8">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Data Mahasiswa</h5>
                <div>
                    @activecan('student-registration.update')
                        <a href="{{ route('admin.academic.student-registrations.edit', ['id' => $registration->id]) }}" class="btn btn-warning ">
                            <i class="fas fa-pencil me-1"></i> Edit
                        </a>
                    @endactivecan
                    <a href="{{ route('admin.academic.student-registrations.index') }}" class="btn btn-secondary ">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">Nama Mahasiswa</small>
                            <div class="h6 mb-0">{{ $registration->studentProfile?->user?->name }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">NIM</small>
                            <div class="h6 mb-0">{{ $registration->studentProfile?->nim }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">Program Studi</small>
                            <div class="h6 mb-0">{{ $registration->studentProfile?->studyProgram?->name }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">Email</small>
                            <div class="h6 mb-0">{{ $registration->studentProfile?->user?->email }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Detail Registrasi</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">Tahun Akademik</small>
                            <div class="h6 mb-0">{{ $registration->academicYear?->name }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">Semester</small>
                            <div class="h6 mb-0">{{ $registration->semester_no ? 'Semester ' . $registration->semester_no : '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">Status Registrasi</small>
                            <div>
                                <span class="badge @if($registration->registration_status === 'Approved') bg-success @elseif($registration->registration_status === 'Rejected') bg-danger @elseif($registration->registration_status === 'Submitted') bg-warning text-dark @elseif($registration->registration_status === 'Cancelled') bg-secondary @else bg-info @endif">
                                    {{ $registration->registration_status }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">Status Akademik</small>
                            <div class="h6 mb-0">{{ $registration->academic_status }}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        @if($registration->notes)
                            <div class="mb-2">
                                <small class="text-muted">Catatan</small>
                                <div class="alert alert-light border mb-0">{{ $registration->notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($registration->registration_status === 'Approved' || $registration->registration_status === 'Rejected')
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Timeline Approval</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        @if($registration->submitted_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <div class="text-muted"><small>Diajukan pada</small></div>
                                    <div>{{ $registration->submitted_at->format('d F Y H:i') }}</div>
                                </div>
                            </div>
                        @endif

                        @if($registration->approved_at)
                            <div class="timeline-item">
                                <div class="timeline-marker @if($registration->registration_status === 'Approved') bg-success @else bg-danger @endif"></div>
                                <div class="timeline-content">
                                    <div class="text-muted"><small>Diproses pada</small></div>
                                    <div>{{ $registration->approved_at->format('d F Y H:i') }}</div>
                                    <div class="text-muted"><small>Oleh: {{ $registration->approvedBy?->name }}</small></div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Metadata</h5>
            </div>
            <div class="card-body">
                <div class="row text-sm">
                    <div class="col-md-6">
                        <small class="text-muted">Dibuat pada:</small>
                        <div class="text-break">{{ $registration->created_at->format('d F Y H:i') }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Diperbarui pada:</small>
                        <div class="text-break">{{ $registration->updated_at->format('d F Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Status Ringkas</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted mb-1">Status Registrasi</div>
                    <span class="badge @if($registration->registration_status === 'Approved') bg-success @elseif($registration->registration_status === 'Rejected') bg-danger @elseif($registration->registration_status === 'Submitted') bg-warning text-dark @elseif($registration->registration_status === 'Cancelled') bg-secondary @else bg-info @endif p-2">
                        {{ $registration->registration_status }}
                    </span>
                </div>

                <div class="mb-3">
                    <div class="text-muted mb-1">Status Akademik</div>
                    <span class="badge bg-light text-dark p-2">{{ $registration->academic_status }}</span>
                </div>

                <div class="mb-3">
                    <div class="text-muted mb-1">Aktif</div>
                    <span class="badge @if($registration->is_active) bg-success @else bg-danger @endif p-2">
                        @if($registration->is_active) ✓ Aktif @else ✗ Nonaktif @endif
                    </span>
                </div>

                @if($registration->registration_status !== 'Approved' && $registration->registration_status !== 'Rejected')
                    <div class="alert alert-warning">
                        <small><strong>Menunggu Approval</strong></small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }

    .timeline-item:not(:last-child)::before {
        content: '';
        position: absolute;
        left: -16px;
        top: 28px;
        bottom: -20px;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-marker {
        position: absolute;
        left: -24px;
        top: 0;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 0 0 2px #e9ecef;
    }
</style>
