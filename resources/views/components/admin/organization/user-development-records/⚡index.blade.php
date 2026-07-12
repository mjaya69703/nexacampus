<?php

use App\Models\Organization\UserDevelopmentRecord;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => UserDevelopmentRecord::count(),
            'verified' => UserDevelopmentRecord::where('is_verified', true)->count(),
            'pending' => UserDevelopmentRecord::where('is_verified', false)->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Sertifikasi & Pelatihan',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Sertifikasi & Pelatihan Pegawai"
        description="Pantau rekam jejak pengembangan kompetensi, sertifikasi profesi, pelatihan eksternal, workshop, dan seminar dari seluruh dosen maupun staf tenaga kependidikan."
        icon="certificate"
    >
        @activecan('user-development-record.create')
            <a href="{{ route('admin.organization.user-development-records.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Riwayat Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-certificate fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Sertifikasi/Pelatihan</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Terverifikasi</div>
                        <div class="fw-bold">{{ number_format($this->stats()['verified']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Menunggu Verifikasi</div>
                        <div class="fw-bold">{{ number_format($this->stats()['pending']) }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Pengembangan Kompetensi SDM</h4>
                    <span class="text-muted small">Gunakan filter pencarian tabel untuk menemukan riwayat sertifikasi berdasarkan nama pegawai, kategori kegiatan, atau status verifikasi.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.user-development-record-table />
        </div>
    </div>
</div>
