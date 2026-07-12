<?php

use Livewire\Component;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Admission\AdmissionApplication;

new class extends Component
{
    public function render()
    {
        $totalPeriods = AdmissionPeriod::count();
        $activePeriods = AdmissionPeriod::where('is_active', true)->count();
        $totalApplications = AdmissionApplication::count();

        return $this->view([
            'totalPeriods' => $totalPeriods,
            'activePeriods' => $activePeriods,
            'totalApplications' => $totalApplications,
        ])->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Gelombang & Periode PMB',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Gelombang & Periode PMB"
        description="Kelola jadwal pembukaan dan penutupan pendaftaran mahasiswa baru per gelombang dan tahun akademik."
        icon="calendar-alt"
    >
        @activecan('admission-period.create')
            <a href="{{ route('admin.admission.admission-periods.create') }}" class="btn btn-light rounded-pill px-4 py-2 text-primary fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
                <i class="fas fa-plus-circle"></i> Tambah Periode Baru
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-layer-group text-warning fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Total Periode</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalPeriods) }} <small class="fs-7 fw-normal">Gelombang</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-check-circle text-success fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Periode Aktif</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($activePeriods) }} <small class="fs-7 fw-normal">Sedang Dibuka</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-users text-info fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Total Pendaftar</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalApplications) }} <small class="fs-7 fw-normal">Aplikasi</small></div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-list text-primary"></i> Daftar Periode Admission
                </h4>
                <p class="text-muted fs-7 mb-0">Gunakan filter pencarian dan toggle status untuk mengaktifkan atau menonaktifkan gelombang secara langsung.</p>
            </div>
        </div>
        <div class="card-body p-4">
            <livewire:admission.admission-period-table />
        </div>
    </div>
</div>
