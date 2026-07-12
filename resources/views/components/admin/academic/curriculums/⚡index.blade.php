<?php

use App\Models\Academic\Curriculum;
use App\Models\Academic\CurriculumCourse;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => Curriculum::count(),
            'active' => Curriculum::where('is_active', true)->count(),
            'total_items' => CurriculumCourse::count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Daftar Kurikulum',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Daftar Kurikulum"
        description="Kelola susunan rancangan kurikulum program studi beserta rentang masa berlaku angkatan/tahun ajaran."
        icon="book-open"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
            @activecan('curriculum.create')
                <a href="{{ route('admin.academic.curriculums.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-plus-circle"></i> <span>Tambah Kurikulum</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-book-open fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Kurikulum</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kurikulum Aktif</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Item Mata Kuliah</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['total_items']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Kurikulum</h4>
                <div class="text-muted small">Statistik singkat kurikulum berdasarkan status aktif dan jumlah item mata kuliah.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran per program studi atau status.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Kurikulum</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($this->stats()['total']) }}</div>
                            <i class="fa fa-book-open fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Kurikulum Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($this->stats()['active']) }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Item Mata Kuliah</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ number_format($this->stats()['total_items']) }}</div>
                            <i class="fa fa-layer-group fs-4 text-info opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Kurikulum</h4>
                    <span class="text-muted small">Gunakan filter pencarian tabel untuk menemukan struktur kurikulum berdasarkan nama, kode, prodi, atau status aktif.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.curriculum-table />
        </div>
    </div>
</div>
