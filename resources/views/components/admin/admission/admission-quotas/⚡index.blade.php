<?php

use Livewire\Component;
use App\Models\Admission\AdmissionQuota;
use App\Support\Admission\AdmissionSelectionService;

new class extends Component
{
    public function render()
    {
        app(AdmissionSelectionService::class)->refreshAcceptedCounts();
        $totalQuota = AdmissionQuota::sum('quota');
        $totalAccepted = AdmissionQuota::sum('accepted_count');
        $totalRemaining = max(0, $totalQuota - $totalAccepted);

        return $this->view([
            'totalQuota' => $totalQuota,
            'totalAccepted' => $totalAccepted,
            'totalRemaining' => $totalRemaining,
        ])->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Kuota Penerimaan Mahasiswa Baru',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Kuota Penerimaan Mahasiswa Baru"
        description="Atur batasan daya tampung per gelombang, fakultas, program studi, serta jenis kelas agar seleksi berjalan proporsional dan akurat."
        icon="chart-pie"
    >
        @activecan('admission-quota.create')
            <a href="{{ route('admin.admission.admission-quotas.create') }}" class="btn btn-light rounded-pill px-4 py-2 text-primary fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
                <i class="fas fa-plus-circle"></i> Tambah Kuota Baru
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-cubes text-warning fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Total Daya Tampung</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalQuota) }} <small class="fs-7 fw-normal">Kursi</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-user-check text-success fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Total Terisi</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalAccepted) }} <small class="fs-7 fw-normal">Calon Mhs</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-door-open text-info fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Sisa Kuota</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalRemaining) }} <small class="fs-7 fw-normal">Kursi Tersedia</small></div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-table text-primary"></i> Rincian Kuota Program Studi
                </h4>
                <p class="text-muted fs-7 mb-0">Pantau persentase keterisian kursi (usage) dan sisa kapasitas tiap program studi secara real-time.</p>
            </div>
        </div>
        <div class="card-body p-4">
            <livewire:admission.quota-table />
        </div>
    </div>
</div>
