<?php

use App\Models\Organization\LecturerWorkloadPeriod;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => LecturerWorkloadPeriod::count(),
            'open' => LecturerWorkloadPeriod::where('status', 'open')->count(),
            'review' => LecturerWorkloadPeriod::where('status', 'review')->count(),
            'closed' => LecturerWorkloadPeriod::where('status', 'closed')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Periode BKD']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Periode Beban Kerja Dosen (BKD)"
        description="Kelola jadwal siklus pembukaan, masa pengajuan, proses review asesor, hingga penutupan pelaporan Beban Kerja Dosen per periode akademik."
        icon="calendar-check"
    >
        @activecan('lecturer-workload-period.create')
            <a href="{{ route('admin.organization.lecturer-workload-periods.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Periode Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Periode</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-door-open fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Dibuka</div>
                        <div class="fw-bold">{{ number_format($this->stats()['open']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-magnifying-glass-chart fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Masa Review</div>
                        <div class="fw-bold">{{ number_format($this->stats()['review']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-lock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Ditutup / Selesai</div>
                        <div class="fw-bold">{{ number_format($this->stats()['closed']) }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Manajemen Siklus BKD</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk mencari periode BKD berdasarkan nama, semester, atau status siklus.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.lecturer-workload-period-table />
        </div>
    </div>
</div>
