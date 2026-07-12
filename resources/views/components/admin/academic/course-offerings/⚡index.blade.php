<?php

use App\Models\Academic\CourseOffering;
use Livewire\Component;

new class extends Component {
    public function stats(): array
    {
        return [
            'total' => CourseOffering::count(),
            'active' => CourseOffering::whereIn('status', ['Terbuka', 'Berjalan', 'Active'])->count(),
            'total_capacity' => CourseOffering::sum('capacity'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Penawaran Kelas Perkuliahan',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Penawaran Kelas Perkuliahan"
        description="Kelola jadwal penawaran kelas mata kuliah, kuota mahasiswa, dosen pengampu, serta mode perkuliahan tiap semester."
        icon="layer-group"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
            @activecan('course-offering.create')
                <a href="{{ route('admin.academic.course-offerings.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-plus-circle"></i> <span>Tambah Kelas Penawaran</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Kelas Ditawarkan</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-door-open fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kelas Aktif/Terbuka</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Kuota Tersedia</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['total_capacity']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Penawaran Kelas</h4>
                <div class="text-muted small">Statistik singkat penawaran kelas berdasarkan status terbuka dan total kuota.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran per mata kuliah atau periode.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Kelas Ditawarkan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($this->stats()['total']) }}</div>
                            <i class="fa fa-layer-group fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Kelas Aktif/Terbuka</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($this->stats()['active']) }}</div>
                            <i class="fa fa-door-open fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Kuota Tersedia</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ number_format($this->stats()['total_capacity']) }}</div>
                            <i class="fa fa-users fs-4 text-info opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Penawaran Kelas Mata Kuliah</h4>
                    <span class="text-muted small">Gunakan filter pencarian tabel untuk menemukan penawaran kelas perkuliahan berdasarkan mata kuliah, periode, atau status kuota.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.course-offering-table />
        </div>
    </div>
</div>
