<?php

use App\Models\Academic\StudentRegistration;
use Livewire\Component;

new class extends Component {
    public function stats(): array
    {
        return [
            'total' => StudentRegistration::count(),
            'active' => StudentRegistration::where('is_active', true)->count(),
            'approved' => StudentRegistration::whereIn('registration_status', ['Approved', 'Disetujui', 'Registered'])->count(),
            'pending' => StudentRegistration::whereIn('registration_status', ['Pending', 'Submitted', 'Menunggu'])->count(),
        ];
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Student Registrations',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Daftar Registrasi Mahasiswa"
        description="Kelola dan pantau status registrasi semester, status akademik aktif/cuti, serta persetujuan her-registrasi mahasiswa."
        icon="user-check"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
            @activecan('student-registration.create')
                <a href="{{ route('admin.academic.student-registrations.create') }}" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm d-inline-flex align-items-center gap-2 fw-semibold">
                    <i class="fa fa-plus"></i> <span>Buat Registrasi Baru</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-list fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Registrasi</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Registrasi Aktif</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clipboard-check fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Disetujui</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['approved']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Registrasi Mahasiswa</h4>
                <div class="text-muted small">Statistik singkat registrasi semester mahasiswa berdasarkan status aktif dan persetujuan.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran per tahun akademik atau status.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Registrasi</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($this->stats()['total']) }}</div>
                            <i class="fa fa-list fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Registrasi Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($this->stats()['active']) }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Disetujui</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ number_format($this->stats()['approved']) }}</div>
                            <i class="fa fa-clipboard-check fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Menunggu / Pending</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-warning lh-1">{{ number_format($this->stats()['pending']) }}</div>
                            <i class="fa fa-clock fs-4 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Data Registrasi & Status Akademik</h4>
                    <span class="text-muted small">Gunakan filter tabel di bawah untuk menelusuri berdasarkan tahun akademik, program studi, maupun status registrasi.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.student-registration-table />
        </div>
    </div>
</div>
