<?php

use App\Models\Academic\Course;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => Course::count(),
            'active' => Course::where('is_active', true)->count(),
            'total_credits' => Course::sum('credits'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Daftar Mata Kuliah',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Daftar Mata Kuliah"
        description="Kelola katalog mata kuliah, bobot SKS, sifat mata kuliah, prasyarat, dan lingkup unit penanggung jawab."
        icon="book"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
            @activecan('course.create')
                <a href="{{ route('admin.academic.courses.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-plus-circle"></i> <span>Tambah Mata Kuliah</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-book fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Mata Kuliah</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Mata Kuliah Aktif</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-graduation-cap fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Akumulasi Kredit (SKS)</div>
                        <div class="fw-bold text-white">{{ number_format($this->stats()['total_credits']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Mata Kuliah</h4>
                <div class="text-muted small">Statistik singkat mata kuliah berdasarkan status aktif dan akumulasi SKS.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran per kurikulum atau status.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Mata Kuliah</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($this->stats()['total']) }}</div>
                            <i class="fa fa-book fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Mata Kuliah Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($this->stats()['active']) }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Akumulasi Kredit (SKS)</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ number_format($this->stats()['total_credits']) }}</div>
                            <i class="fa fa-graduation-cap fs-4 text-info opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Mata Kuliah</h4>
                    <span class="text-muted small">Gunakan filter pencarian tabel untuk menemukan mata kuliah berdasarkan kode, nama, kurikulum, atau status aktif.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.course-table />
        </div>
    </div>
</div>
