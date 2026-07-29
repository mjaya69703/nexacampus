<?php

use App\Models\Campus\CampusLocation;
use App\Models\Campus\Building;
use Livewire\Component;

new class extends Component
{
    public int $totalCount = 0;
    public int $activeCount = 0;
    public int $mainCount = 0;
    public int $buildingCount = 0;

    public function mount(): void
    {
        $this->totalCount = CampusLocation::count();
        $this->activeCount = CampusLocation::where('is_active', true)->count();
        $this->mainCount = CampusLocation::where('is_main', true)->count();
        $this->buildingCount = Building::count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Kampus & Lokasi',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.campus.header
        title="Manajemen Kampus & Lokasi"
        description="Kelola lokasi kampus utama dan cabang, alamat, kontak, koordinat GPS geofencing, serta keterhubungan dengan gedung."
        icon="location-dot"
    >
        @activecan('campus-location.create')
            <a href="{{ route('admin.campus.locations.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Lokasi Kampus</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-location-dot fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Lokasi</div>
                        <div class="fw-bold">{{ $totalCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Lokasi Aktif</div>
                        <div class="fw-bold">{{ $activeCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-star fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kampus Utama</div>
                        <div class="fw-bold">{{ $mainCount }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.campus.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Daftar Lokasi Kampus</h4>
                    <span class="text-muted small">Tabel data lokasi kampus, status aktif, dan koordinat GPS.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:campus.campus-locations-table />
        </div>
    </div>
</div>
