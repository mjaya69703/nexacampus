<?php

use App\Models\StudentService\StudentComplaint;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => StudentComplaint::count(),
            'open' => StudentComplaint::whereNotIn('status', ['closed', 'rejected'])->count(),
            'waiting' => StudentComplaint::where('status', 'waiting_student')->count(),
            'overdue' => StudentComplaint::whereNotIn('status', ['resolved', 'closed', 'rejected'])->whereNotNull('due_at')->where('due_at', '<', now())->count(),
        ];
    }

    public function refreshComplaintTable(): void
    {
        $this->dispatch('pg:eventRefresh-studentComplaintTable');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Layanan Mahasiswa', 'pages' => 'Pengaduan']);
    }
};
?>

<div class="w-full" style="width: 100% !important" wire:poll.5s="refreshComplaintTable">
    <x-alert />
    <x-admin.student-services.header
        title="Pengaduan & Tiket Layanan"
        description="Pantau pengaduan mahasiswa, alokasi ke unit kerja terkait, responsibilitas penanganan, dan ketepatan SLA."
        icon="headset"
    >
        <span class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 px-3 py-2 rounded-pill shadow-sm d-inline-flex align-items-center gap-1">
            <i class="fa fa-rotate me-1"></i> Auto Refresh 5s
        </span>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-ticket fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Semua Tiket</div>
                        <div class="fw-bold">{{ $this->stats()['total'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-inbox fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tiket Aktif</div>
                        <div class="fw-bold">{{ $this->stats()['open'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Menunggu Mhs</div>
                        <div class="fw-bold">{{ $this->stats()['waiting'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-triangle-exclamation fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Overdue SLA</div>
                        <div class="fw-bold">{{ $this->stats()['overdue'] }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Tiket Layanan</h4>
                <div class="text-muted small">Statistik penanganan pengaduan dan pemantauan SLA.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter kategori dan unit tujuan untuk penanganan tepat sasaran.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Semua Tiket</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['total'] }}</div>
                            <i class="fa fa-ticket fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Tiket Aktif / Open</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['open'] }}</div>
                            <i class="fa fa-inbox fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Menunggu Balasan Mhs</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['waiting'] }}</div>
                            <i class="fa fa-user-clock fs-4 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Lewat Batas SLA (Overdue)</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['overdue'] }}</div>
                            <i class="fa fa-triangle-exclamation fs-4 text-danger opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Daftar Tiket Pengaduan</h4>
                    <span class="text-muted small">Kelola penugasan tiket, pemantauan status balasan, dan eskalasi pengaduan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:student-service.student-complaint-table />
        </div>
    </div>
</div>
