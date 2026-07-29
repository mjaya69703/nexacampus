<?php

use App\Models\Campus\CampusAsset;
use Livewire\Component;

new class extends Component
{
    public int $totalCount = 0;
    public int $goodCount = 0;
    public int $damageCount = 0;
    public int $activeCount = 0;

    public function mount(): void
    {
        $this->totalCount = CampusAsset::count();
        $this->goodCount = CampusAsset::where('condition', 'good')->count();
        $this->damageCount = CampusAsset::whereIn('condition', ['minor_damage', 'major_damage'])->count();
        $this->activeCount = CampusAsset::where('status', 'active')->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Aset & Fasilitas Kampus',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.campus.header
        title="Manajemen Aset & Fasilitas Kampus"
        description="Kelola inventaris barang, perlengkapan perkuliahan, kondisi alat (baik/rusak), serta lokasi penempatan di setiap ruangan."
        icon="boxes-packing"
    >
        @activecan('campus-asset.create')
            <a href="{{ route('admin.campus.assets.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Aset Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-boxes-packing fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Aset</div>
                        <div class="fw-bold">{{ $totalCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kondisi Baik</div>
                        <div class="fw-bold text-success">{{ $goodCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-triangle-exclamation fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Perlu Perbaikan</div>
                        <div class="fw-bold text-warning">{{ $damageCount }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Daftar Aset & Fasilitas</h4>
                    <span class="text-muted small">Tabel data aset, lokasi ruangan, kondisi, dan status inventaris.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:campus.campus-assets-table />
        </div>
    </div>
</div>
