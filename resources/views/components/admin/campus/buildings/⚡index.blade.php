<?php

use App\Models\Campus\Building;
use App\Models\Campus\Room;
use Livewire\Component;

new class extends Component
{
    public int $buildingCount = 0;

    public int $activeBuildingCount = 0;

    public int $roomCount = 0;

    public int $totalFloorCount = 0;

    public function mount(): void
    {
        $this->buildingCount = Building::count();
        $this->activeBuildingCount = Building::query()->where('is_active', true)->count();
        $this->roomCount = Room::count();
        $this->totalFloorCount = (int) Building::query()->sum('floor_count');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Daftar Gedung',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.campus.header
        title="Manajemen Gedung Kampus"
        description="Kelola seluruh gedung kampus, status aktif, jumlah lantai, dan hubungan ke ruangan agar penjadwalan dan pemakaian fasilitas tetap tertib."
        icon="building"
    >
        @activecan('building.create')
            <a href="{{ route('admin.campus.buildings.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Gedung</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-building fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Gedung</div>
                        <div class="fw-bold">{{ $buildingCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Gedung Aktif</div>
                        <div class="fw-bold">{{ $activeBuildingCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-door-open fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Ruangan</div>
                        <div class="fw-bold">{{ $roomCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Lantai Tercatat</div>
                        <div class="fw-bold">{{ $totalFloorCount }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.campus.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Gedung</h4>
                <div class="text-muted small">Statistik singkat untuk memantau kapasitas dan kesiapan fasilitas kampus.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter dan toggle status untuk menjaga data gedung tetap akurat.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Gedung</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $buildingCount }}</div>
                            <i class="fa fa-building fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Gedung Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $activeBuildingCount }}</div>
                            <i class="fa fa-circle-check fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Ruangan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $roomCount }}</div>
                            <i class="fa fa-door-open fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Lantai</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalFloorCount }}</div>
                            <i class="fa fa-layer-group fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Gedung</h4>
                    <span class="text-muted small">Nama gedung, kode, jumlah ruangan, status, dan aksi pengelolaan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:campus.buildings-table />
        </div>
    </div>
</div>
