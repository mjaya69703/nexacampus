<?php

use App\Models\Academic\StudyProgram;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => StudyProgram::count(),
            'active' => StudyProgram::where('is_active', true)->count(),
            's1' => StudyProgram::where('degree', 'S1')->count(),
            'd3' => StudyProgram::where('degree', 'D3')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Program Studi',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Daftar Program Studi"
        description="Kelola seluruh program studi beserta jenjang pendidikan (D3, S1, S2, dsb) di bawah naungan masing-masing fakultas."
        icon="graduation-cap"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
            @activecan('study-program.create')
                <a href="{{ route('admin.academic.study-programs.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-plus-circle"></i> <span>Tambah Program Studi</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-graduation-cap fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Program Studi</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Prodi Aktif</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-graduate fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Jenjang S1</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['s1']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-award fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Jenjang D3</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['d3']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Program Studi</h4>
                <div class="text-muted small">Statistik singkat program studi berdasarkan status aktif dan jenjang pendidikan.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran per fakultas atau jenjang.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Prodi</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($this->stats()['total']) }}</div>
                            <i class="fa fa-graduation-cap fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Prodi Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($this->stats()['active']) }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Jenjang S1</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ number_format($this->stats()['s1']) }}</div>
                            <i class="fa fa-user-graduate fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Jenjang D3</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-warning lh-1">{{ number_format($this->stats()['d3']) }}</div>
                            <i class="fa fa-award fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Program Studi</h4>
                    <span class="text-muted small">Gunakan filter pencarian tabel untuk menemukan program studi berdasarkan nama, kode, fakultas, atau status aktif.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.study-program-table />
        </div>
    </div>
</div>
