<?php

use App\Models\Alumni\AlumniProfile;
use Livewire\Component;

new class extends Component
{
    public int $totalAlumni = 0;
    public int $activeAlumni = 0;
    public int $employedAlumni = 0;
    public int $entrepreneurAlumni = 0;

    public function mount(): void
    {
        $this->totalAlumni = AlumniProfile::count();
        $this->activeAlumni = AlumniProfile::query()->where('is_active', true)->count();
        $this->employedAlumni = AlumniProfile::query()->where('employment_status', 'employed')->count();
        $this->entrepreneurAlumni = AlumniProfile::query()->where('employment_status', 'entrepreneur')->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Daftar Profil Alumni',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Manajemen Profil Alumni"
        description="Kelola direktori alumni, pemutakhiran data karir, status kepekerjaan, serta penelusuran lulusan kampus secara terpadu."
        icon="user-graduate"
    >
        @activecan('alumni-profile.create')
            <a href="{{ route('admin.alumni.profiles.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Alumni</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Alumni</div>
                        <div class="fw-bold">{{ $totalAlumni }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Alumni Aktif</div>
                        <div class="fw-bold">{{ $activeAlumni }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-briefcase fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Bekerja (Employed)</div>
                        <div class="fw-bold">{{ $employedAlumni }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-store fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Wirausaha</div>
                        <div class="fw-bold">{{ $entrepreneurAlumni }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Status Lulusan</h4>
                <div class="text-muted small">Monitoring penyebaran status karir alumni yang terdaftar di dalam database.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter tabel di bawah untuk pencarian spesifik per program studi atau tahun lulus.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Alumni Terdaftar</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalAlumni }}</div>
                            <i class="fa fa-user-graduate fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Status Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $activeAlumni }}</div>
                            <i class="fa fa-circle-check fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Bekerja / Perusahaan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $employedAlumni }}</div>
                            <i class="fa fa-building fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Wirausaha / Mandiri</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $entrepreneurAlumni }}</div>
                            <i class="fa fa-chart-line fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Profil Alumni</h4>
                    <span class="text-muted small">Daftar lengkap lulusan beserta NIM, program studi, tahun lulus, IPK, dan status pekerjaan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:alumni.alumni-profile-table />
        </div>
    </div>
</div>
