<?php

use App\Models\Campus\Room;
use Livewire\Component;

new class extends Component
{
    public int $roomCount = 0;

    public int $activeRoomCount = 0;

    public int $buildingLinkedCount = 0;

    public int $totalCapacity = 0;

    public function mount(): void
    {
        $this->roomCount = Room::count();
        $this->activeRoomCount = Room::query()->where('is_active', true)->count();
        $this->buildingLinkedCount = Room::query()->whereNotNull('building_id')->distinct('building_id')->count('building_id');
        $this->totalCapacity = (int) Room::query()->sum('capacity');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Daftar Ruangan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.campus.header
        title="Manajemen Ruangan Kampus"
        description="Kelola ruang kuliah, laboratorium, kantor, dan ruangan lain dengan informasi gedung, kapasitas, tipe, serta status aktif yang siap dipakai untuk penjadwalan."
        icon="door-open"
    >
        @activecan('room.create')
            <a href="{{ route('admin.campus.rooms.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Ruangan</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-door-open fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Ruangan</div>
                        <div class="fw-bold">{{ $roomCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Ruangan Aktif</div>
                        <div class="fw-bold">{{ $activeRoomCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-building fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Gedung Terhubung</div>
                        <div class="fw-bold">{{ $buildingLinkedCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kapasitas Total</div>
                        <div class="fw-bold">{{ $totalCapacity }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.campus.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Ruangan</h4>
                <div class="text-muted small">Informasi singkat yang membantu memastikan ruangan siap dipakai sesuai kebutuhan kampus.</div>
            </div>
            <div class="text-muted small">
                Filter berdasarkan gedung, tipe, atau status untuk menemukan ruangan lebih cepat.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Ruangan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $roomCount }}</div>
                            <i class="fa fa-door-open fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Ruangan Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $activeRoomCount }}</div>
                            <i class="fa fa-circle-check fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Gedung Terhubung</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $buildingLinkedCount }}</div>
                            <i class="fa fa-building fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Kapasitas Total</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalCapacity }}</div>
                            <i class="fa fa-users fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Ruangan</h4>
                    <span class="text-muted small">Nama ruangan, gedung, kapasitas, tipe, status, dan aksi pengelolaan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:campus.rooms-table />
        </div>
    </div>
</div>
