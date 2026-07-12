<?php

use Livewire\Component;
use App\Models\Admission\AdmissionApplication;

new class extends Component
{
    public function render()
    {
        $totalApps = AdmissionApplication::count();
        $underReview = AdmissionApplication::where('status', 'under_review')->count();
        $accepted = AdmissionApplication::where('status', 'accepted')->count();
        $converted = AdmissionApplication::whereNotNull('converted_at')->count();

        return $this->view([
            'totalApps' => $totalApps,
            'underReview' => $underReview,
            'accepted' => $accepted,
            'converted' => $converted,
        ])->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Aplikasi Pendaftaran Mahasiswa Baru',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Aplikasi Pendaftaran Mahasiswa Baru"
        description="Pantau seluruh masuknya berkas pendaftar, verifikasi dokumen, nilai seleksi, serta konversi massal calon mahasiswa menjadi mahasiswa aktif."
        icon="user-graduate"
    >
        <a href="{{ route('admission.apply') }}" class="btn btn-light rounded-pill px-4 py-2 text-primary fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0" target="_blank">
            <i class="fas fa-external-link-alt"></i> Form Pendaftaran Publik
        </a>

        <x-slot:stats>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-file-signature text-warning fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Total Pendaftar</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalApps) }} <small class="fs-7 fw-normal">Aplikasi</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-spinner text-info fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Proses Seleksi</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($underReview) }} <small class="fs-7 fw-normal">Berkas</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-check-double text-success fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Diterima</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($accepted) }} <small class="fs-7 fw-normal">Calon Mhs</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-id-card text-white fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Sudah Dikonversi</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($converted) }} <small class="fs-7 fw-normal">NIM Aktif</small></div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-users text-primary"></i> Data Pendaftar Masuk
                </h4>
                <p class="text-muted fs-7 mb-0">Gunakan filter di atas tabel untuk memilah pendaftar berdasarkan gelombang, program studi, status seleksi, maupun kelas.</p>
            </div>
        </div>
        <div class="card-body p-4">
            <livewire:admission.application-table />
        </div>
    </div>
</div>
