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
            'menus' => 'Alumni',
            'pages' => 'Detail Profil Alumni',
        ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Profil Alumni</h5>
                <div>
                    @activecan('alumni-profile.update')
                    <a href="{{ route('admin.alumni.profiles.edit', ['id' => $profile->id]) }}" class="btn btn-warning">
                        <i class="fas fa-pencil me-1"></i> Edit
                    </a>
                    @endactivecan
                    <button class="btn btn-secondary" wire:click="goBack">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">NIM</label>
                        <div class="h6 mb-0">{{ $profile->nim }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Nama Lengkap</label>
                        <div class="h6 mb-0">{{ $profile->full_name }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Email</label>
                        <div class="h6 mb-0">{{ $profile->email }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Tanggal Lulus</label>
                        <div class="h6 mb-0">{{ $profile->graduation_date?->format('d M Y') ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Tahun Lulus</label>
                        <div class="h6 mb-0">{{ $profile->graduation_year ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">IPK</label>
                        <div class="h6 mb-0">{{ $profile->gpa ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Jenis Kelamin</label>
                        <div class="h6 mb-0">{{ $profile->gender === 'L' ? 'Laki-laki' : ($profile->gender === 'P' ? 'Perempuan' : '-') }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Fakultas</label>
                        <div class="h6 mb-0">{{ $profile->faculty?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Program Studi</label>
                        <div class="h6 mb-0">{{ $profile->studyProgram?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">No. Telepon</label>
                        <div class="h6 mb-0">{{ $profile->phone ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Kota</label>
                        <div class="h6 mb-0">{{ $profile->current_city ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Provinsi</label>
                        <div class="h6 mb-0">{{ $profile->current_province ?? '-' }}</div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Alamat</label>
                        <div class="p-2 bg-light rounded">{{ $profile->address ?: '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Status Pekerjaan</label>
                        <div>
                            <span class="badge bg-primary">{{ $employmentStatuses[$profile->employment_status] ?? $profile->employment_status }}</span>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Perusahaan</label>
                        <div class="h6 mb-0">{{ $profile->employer_name ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Jabatan</label>
                        <div class="h6 mb-0">{{ $profile->job_title ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Industri</label>
                        <div class="h6 mb-0">{{ $profile->job_industry ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">LinkedIn</label>
                        <div class="h6 mb-0">
                            @if ($profile->linkedin_url)
                                <a href="{{ $profile->linkedin_url }}" target="_blank">{{ $profile->linkedin_url }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            @if ($profile->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Nonaktif</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Kelengkapan Profil</label>
                        <div class="h6 mb-0">{{ $profile->profileCompleteness() }}%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
