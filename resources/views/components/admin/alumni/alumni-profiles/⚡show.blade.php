<?php

use App\Enums\EmploymentStatus;
use App\Models\Alumni\AlumniProfile;
use Livewire\Component;

new class extends Component
{
    public AlumniProfile $profile;
    public array $employmentStatuses = [];

    public function mount($id): void
    {
        $this->profile = AlumniProfile::query()
            ->with(['studyProgram', 'faculty', 'user'])
            ->findOrFail($id);
        $this->employmentStatuses = EmploymentStatus::options();
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.alumni.profiles.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Detail Profil Alumni',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Detail Alumni: {{ $profile->full_name }}"
        description="Informasi lengkap biodata lulusan, rekam jejak akademik selama perkuliahan, serta penelusuran karir dan kepekerjaan."
        icon="id-badge"
    >
        @activecan('alumni-profile.update')
            <a href="{{ route('admin.alumni.profiles.edit', ['id' => $profile->id]) }}" class="btn btn-sm btn-light text-warning fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-edit"></i> <span>Edit Profil</span>
            </a>
        @endactivecan
        <button type="button" wire:click="goBack" class="btn btn-sm btn-outline-light text-white border-opacity-50 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </button>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-id-card fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">NIM Alumni</div>
                        <div class="fw-bold">{{ $profile->nim }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-graduation-cap fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tahun Lulus / IPK</div>
                        <div class="fw-bold">{{ $profile->graduation_year ?? '-' }} / {{ $profile->gpa ?? '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-briefcase fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Karir</div>
                        <div class="fw-bold">{{ $employmentStatuses[$profile->employment_status] ?? $profile->employment_status }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-percent fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kelengkapan Data</div>
                        <div class="fw-bold">{{ $profile->profileCompleteness() }}%</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-user-circle me-2 text-primary"></i>Informasi Pribadi & Akademik</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">NIM</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->nim }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Nama Lengkap</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->full_name }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Email</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->email }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Tanggal & Tahun Lulus</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->graduation_date?->format('d M Y') ?? '-' }} ({{ $profile->graduation_year ?? '-' }})</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">IPK Akhir</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->gpa ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Jenis Kelamin</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->gender === 'L' ? 'Laki-laki' : ($profile->gender === 'P' ? 'Perempuan' : '-') }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Fakultas</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->faculty?->name ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Program Studi</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->studyProgram?->name ?? '-' }}</div>
                        </div>
                        <div class="col-12 mt-2">
                            <label class="form-label text-muted small mb-1">Alamat Domisili</label>
                            <div class="p-3 bg-light rounded-3 text-dark">{{ $profile->address ?: 'Alamat belum tercatat.' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Kota</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->current_city ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Provinsi</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->current_province ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">No. Telepon</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->phone ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-briefcase me-2 text-success"></i>Status Kepekerjaan & Karir Terkini</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Status Pekerjaan</label>
                            <div>
                                <span class="badge bg-primary px-3 py-1.5 rounded-pill fs-6">{{ $employmentStatuses[$profile->employment_status] ?? $profile->employment_status }}</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Nama Perusahaan / Instansi</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->employer_name ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Posisi / Jabatan</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->job_title ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Bidang Industri</label>
                            <div class="fw-bold fs-6 text-dark">{{ $profile->job_industry ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Profil LinkedIn</label>
                            <div>
                                @if ($profile->linkedin_url)
                                    <a href="{{ $profile->linkedin_url }}" target="_blank" class="fw-semibold text-primary d-inline-flex align-items-center gap-1">
                                        <i class="fab fa-linkedin fs-5"></i> <span>Lihat Profil LinkedIn</span>
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden text-center p-4 bg-white">
                @if ($profile->photo_path)
                    <img src="{{ Storage::url($profile->photo_path) }}" alt="Foto {{ $profile->full_name }}" class="rounded-circle object-fit-cover mx-auto mb-3 shadow-sm border" style="width: 120px; height: 120px;">
                @else
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold fs-2 shadow-sm" style="width: 120px; height: 120px;">
                        {{ strtoupper(substr($profile->full_name, 0, 2)) }}
                    </div>
                @endif
                <h5 class="fw-bold mb-1 text-dark">{{ $profile->full_name }}</h5>
                <p class="text-muted small mb-3">{{ $profile->nim }} • {{ $profile->studyProgram?->name ?? '-' }}</p>
                <div>
                    @if ($profile->is_active)
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1.5 rounded-pill"><i class="fa fa-circle me-1 small"></i> Status Keanggotaan Aktif</span>
                    @else
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1.5 rounded-pill"><i class="fa fa-circle me-1 small"></i> Status Keanggotaan Nonaktif</span>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fa fa-percent me-2 text-info"></i>Tingkat Kelengkapan Profil</h6>
                    <div class="progress rounded-pill mb-2" style="height: 10px;">
                        <div class="progress-bar bg-info" role="progressbar" style="width: {{ $profile->profileCompleteness() }}%;" aria-valuenow="{{ $profile->profileCompleteness() }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small">
                        <span>Status data:</span>
                        <span class="fw-bold text-dark">{{ $profile->profileCompleteness() }}% Lengkap</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
