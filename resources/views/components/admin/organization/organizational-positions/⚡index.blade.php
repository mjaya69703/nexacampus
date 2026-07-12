<?php

use App\Models\Organization\OrganizationalPosition;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => OrganizationalPosition::count(),
            'active' => OrganizationalPosition::where('is_active', true)->count(),
            'scoped' => OrganizationalPosition::where('scope_type', '!=', 'none')->count(),
            'assignments' => OrganizationalPosition::query()->withCount('assignments')->get()->sum('assignments_count'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Jabatan',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Jabatan Struktural & Fungsional"
        description="Definisikan master jabatan organisasi, cakupan wewenang (scope fakultas/prodi/unit kerja), serta struktur penugasan bagi para pegawai."
        icon="user-tie"
    >
        @activecan('organizational-position.create')
            <a href="{{ route('admin.organization.organizational-positions.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Jabatan</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-tie fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Jabatan</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Aktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-sitemap fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Jabatan Berscope</div>
                        <div class="fw-bold">{{ number_format($this->stats()['scoped']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clipboard-user fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Penugasan</div>
                        <div class="fw-bold">{{ number_format($this->stats()['assignments']) }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Master Jabatan</h4>
                    <span class="text-muted small">Gunakan filter pencarian tabel untuk menemukan jabatan berdasarkan kode, nama, atau lingkup wewenang.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.organizational-position-table />
        </div>
    </div>
</div>
