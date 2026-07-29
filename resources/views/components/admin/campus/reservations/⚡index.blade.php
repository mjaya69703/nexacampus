<?php

use App\Models\Campus\RoomReservation;
use Livewire\Component;

new class extends Component
{
    public int $totalCount = 0;
    public int $pendingCount = 0;
    public int $approvedCount = 0;
    public int $rejectedCount = 0;

    public function mount(): void
    {
        $this->totalCount = RoomReservation::count();
        $this->pendingCount = RoomReservation::where('status', 'pending')->count();
        $this->approvedCount = RoomReservation::where('status', 'approved')->count();
        $this->rejectedCount = RoomReservation::where('status', 'rejected')->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Peminjaman Ruangan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.campus.header
        title="Manajemen Peminjaman Ruangan"
        description="Kelola permohonan reservasi ruangan kampus, verifikasi jadwal, persetujuan (approval), dan cegah terjadinya bentrok pemakaian ruangan."
        icon="calendar-check"
    >
        @activecan('room-reservation.create')
            <a href="{{ route('admin.campus.reservations.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Buat Reservasi Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Reservasi</div>
                        <div class="fw-bold">{{ $totalCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Menunggu Approval</div>
                        <div class="fw-bold text-warning">{{ $pendingCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Disetujui</div>
                        <div class="fw-bold text-success">{{ $approvedCount }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Daftar Peminjaman Ruangan</h4>
                    <span class="text-muted small">Tabel riwayat permohonan reservasi ruangan dan status persetujuan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:campus.room-reservations-table />
        </div>
    </div>
</div>
