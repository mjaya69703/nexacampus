<?php

use App\Models\Alumni\JobPosting;
use Livewire\Component;

new class extends Component
{
    public int $totalJobs = 0;
    public int $activeJobs = 0;
    public int $fullTimeJobs = 0;
    public int $internshipJobs = 0;

    public function mount(): void
    {
        $this->totalJobs = JobPosting::count();
        $this->activeJobs = JobPosting::query()->where('is_active', true)->count();
        $this->fullTimeJobs = JobPosting::query()->where('job_type', 'full_time')->count();
        $this->internshipJobs = JobPosting::query()->where('job_type', 'internship')->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Lowongan Pekerjaan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Bursa Kerja & Lowongan Karir"
        description="Publikasi dan pengelolaan informasi lowongan pekerjaan dari perusahaan mitra maupun institusi eksternal untuk alumni."
        icon="briefcase"
    >
        @activecan('job-posting.create')
            <a href="{{ route('admin.alumni.job-postings.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Lowongan Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-briefcase fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Lowongan</div>
                        <div class="fw-bold">{{ $totalJobs }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Lowongan Aktif</div>
                        <div class="fw-bold">{{ $activeJobs }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-tie fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Full Time</div>
                        <div class="fw-bold">{{ $fullTimeJobs }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-graduate fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Magang / Intern</div>
                        <div class="fw-bold">{{ $internshipJobs }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Lowongan Kerja</h4>
                <div class="text-muted small">Sebaran posisi dan peluang karir yang sedang terbuka bagi lulusan perguruan tinggi.</div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Lowongan Terdaftar</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalJobs }}</div>
                            <i class="fa fa-briefcase fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Status Buka (Aktif)</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $activeJobs }}</div>
                            <i class="fa fa-circle-check fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Posisi Full Time</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $fullTimeJobs }}</div>
                            <i class="fa fa-building fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Program Magang</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $internshipJobs }}</div>
                            <i class="fa fa-graduation-cap fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Lowongan Pekerjaan</h4>
                    <span class="text-muted small">Judul posisi, nama perusahaan, tipe pekerjaan, lokasi, deadline lamaran, dan status.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:alumni.job-posting-table />
        </div>
    </div>
</div>
