<?php

use App\Models\Campus\FacilityMaintenanceTicket;
use Livewire\Component;

new class extends Component
{
    public int $totalCount = 0;
    public int $openCount = 0;
    public int $progressCount = 0;
    public int $resolvedCount = 0;

    public function mount(): void
    {
        $this->totalCount = FacilityMaintenanceTicket::count();
        $this->openCount = FacilityMaintenanceTicket::where('status', 'open')->count();
        $this->progressCount = FacilityMaintenanceTicket::where('status', 'in_progress')->count();
        $this->resolvedCount = FacilityMaintenanceTicket::where('status', 'resolved')->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Laporan Kerusakan & Maintenance',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.campus.header
        title="Laporan Kerusakan & Maintenance Kampus"
        description="Kelola tiket pengaduan kerusakan sarana prasarana kampus, penugasan teknisi maintenance, serta histori perbaikan dan biaya."
        icon="screwdriver-wrench"
    >
        @activecan('facility-maintenance.create')
            <a href="{{ route('admin.campus.maintenance.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Buat Laporan Kerusakan</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-screwdriver-wrench fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Tiket</div>
                        <div class="fw-bold">{{ $totalCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-envelope-open fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tiket Terbuka</div>
                        <div class="fw-bold text-warning">{{ $openCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-gears fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Dalam Pengerjaan</div>
                        <div class="fw-bold text-info">{{ $progressCount }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Daftar Laporan Kerusakan</h4>
                    <span class="text-muted small">Tabel tiket pengaduan fasilitas, prioritas pengerjaan, dan status perbaikan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:campus.facility-maintenance-tickets-table />
        </div>
    </div>
</div>
