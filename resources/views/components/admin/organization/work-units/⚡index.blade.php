<?php

use App\Models\Organization\WorkUnit;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => WorkUnit::count(),
            'active' => WorkUnit::where('is_active', true)->count(),
            'inactive' => WorkUnit::where('is_active', false)->count(),
            'members' => WorkUnit::query()->withCount('activeMembers')->get()->sum('active_members_count'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Unit Kerja',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Unit Kerja & Biro Kampus"
        description="Kelola unit operasional, biro, lembaga, atau pusat studi kampus untuk penugasan kepegawaian, alur persetujuan (approval), dan pembatasan akses lingkup data."
        icon="building"
    >
        @activecan('work-unit.create')
            <a href="{{ route('admin.organization.work-units.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Unit Kerja</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-building fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Unit Kerja</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Unit Aktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-pause fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Unit Nonaktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['inactive']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Anggota Aktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['members']) }} Pegawai</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Unit Kerja & Lembaga</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk mencari unit kerja berdasarkan kode, nama, atau status keaktifan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.work-unit-table />
        </div>
    </div>
</div>
