<?php

use App\Models\Organization\LecturerWorkloadRule;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => LecturerWorkloadRule::count(),
            'active' => LecturerWorkloadRule::where('is_active', true)->count(),
            'teaching' => LecturerWorkloadRule::where('category', 'teaching')->count(),
            'tridharma' => LecturerWorkloadRule::whereIn('category', ['research', 'service', 'supporting'])->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Aturan SKS BKD']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Aturan Konversi SKS BKD"
        description="Atur regulasi konversi poin, kuota SKS maksimal per item, serta kategori aktivitas tri dharma (pengajaran, penelitian, pengabdian, penunjang, dan jabatan struktural)."
        icon="cogs"
    >
        @activecan('lecturer-workload-rule.create')
            <a href="{{ route('admin.organization.lecturer-workload-rules.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Aturan Konversi</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-cogs fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Aturan</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Aturan Aktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-chalkboard-user fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kategori Pengajaran</div>
                        <div class="fw-bold">{{ number_format($this->stats()['teaching']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-award fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tridharma Lainnya</div>
                        <div class="fw-bold">{{ number_format($this->stats()['tridharma']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Regulasi Konversi SKS</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk mencari aturan berdasarkan kategori atau status keaktifan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.lecturer-workload-rule-table />
        </div>
    </div>
</div>
