<?php

use App\Enums\JobType;
use App\Models\Alumni\JobPosting;
use Livewire\Component;

new class extends Component
{
    public JobPosting $posting;
    public array $jobTypes = [];

    public function mount($id): void
    {
        $this->posting = JobPosting::query()
            ->with(['employerPartner'])
            ->findOrFail($id);
        $this->jobTypes = JobType::options();
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.alumni.job-postings.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Detail Lowongan Kerja',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Detail Lowongan: {{ $posting->title }}"
        description="Informasi spesifikasi pekerjaan, kualifikasi kandidat, serta tenggat waktu lamaran di {{ $posting->company_name }}."
        icon="file-contract"
    >
        @activecan('job-posting.update')
            <a href="{{ route('admin.alumni.job-postings.edit', ['id' => $posting->id]) }}" class="btn btn-sm btn-light text-warning fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-edit"></i> <span>Edit Lowongan</span>
            </a>
        @endactivecan
        <button type="button" wire:click="goBack" class="btn btn-sm btn-outline-light text-white border-opacity-50 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </button>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-building fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Perusahaan</div>
                        <div class="fw-bold">{{ $posting->company_name }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-map-marker-alt fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Lokasi Kerja</div>
                        <div class="fw-bold">{{ $posting->location ?: '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-briefcase fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tipe Pekerjaan</div>
                        <div class="fw-bold">{{ $jobTypes[$posting->job_type] ?? $posting->job_type ?? '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-times fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Batas Akhir (Deadline)</div>
                        <div class="fw-bold">{{ $posting->deadline_date?->format('d M Y') ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-info-circle me-2 text-primary"></i>Informasi Umum Lowongan</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Judul Posisi</label>
                            <div class="fw-bold fs-6 text-dark">{{ $posting->title }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Nama Perusahaan / Instansi</label>
                            <div class="fw-bold fs-6 text-dark">{{ $posting->company_name }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Mitra Terhubung</label>
                            <div class="fw-bold fs-6 text-dark">{{ $posting->employerPartner?->name ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Sektor Industri</label>
                            <div class="fw-bold fs-6 text-dark">{{ $posting->industry ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Tipe Kerja</label>
                            <div>
                                <span class="badge bg-primary px-3 py-1.5 rounded-pill fs-6">{{ $jobTypes[$posting->job_type] ?? $posting->job_type ?? '-' }}</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Lokasi Penempatan</label>
                            <div class="fw-bold fs-6 text-dark">{{ $posting->location ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Rentang Gaji Ditawarkan</label>
                            <div class="fw-bold fs-6 text-dark">{{ $posting->salary_range ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Tanggal Dipublikasi</label>
                            <div class="fw-bold fs-6 text-dark">{{ $posting->posted_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Deadline Lamaran</label>
                            <div class="fw-bold fs-6 text-dark">{{ $posting->deadline_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Email Pengiriman CV</label>
                            <div class="fw-bold fs-6 text-dark">{{ $posting->contact_email ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-align-left me-2 text-warning"></i>Deskripsi Pekerjaan (Job Description)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-dark lh-lg bg-light p-3 rounded-3">{!! nl2br(e($posting->description)) !!}</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-tasks me-2 text-info"></i>Kualifikasi & Persyaratan (Requirements)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-dark lh-lg bg-light p-3 rounded-3">{!! nl2br(e($posting->requirements ?? 'Tidak ada persyaratan khusus tertulis.')) !!}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden text-center p-4 bg-white">
                @if ($posting->employerPartner?->logo_path)
                    <img src="{{ Storage::url($posting->employerPartner->logo_path) }}" alt="Logo {{ $posting->company_name }}" class="rounded mx-auto mb-3 object-fit-contain bg-light p-2 shadow-sm border" style="width: 110px; height: 110px;">
                @else
                    <div class="bg-primary bg-opacity-10 text-primary rounded d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold fs-2 shadow-sm" style="width: 110px; height: 110px;">
                        <i class="fa fa-building fs-1"></i>
                    </div>
                @endif
                <h5 class="fw-bold mb-1 text-dark">{{ $posting->company_name }}</h5>
                <p class="text-muted small mb-3">{{ $posting->industry ?: 'Sektor Umum' }} • {{ $posting->location ?: 'Indonesia' }}</p>

                <div class="mb-3">
                    @if ($posting->is_active)
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1.5 rounded-pill"><i class="fa fa-check-circle me-1 small"></i> Lowongan Terbuka & Aktif</span>
                    @else
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1.5 rounded-pill"><i class="fa fa-circle-xmark me-1 small"></i> Lowongan Ditutup / Nonaktif</span>
                    @endif
                </div>

                @if ($posting->apply_url)
                    <a href="{{ $posting->apply_url }}" target="_blank" class="btn btn-primary rounded-pill w-100 py-2.5 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="fa fa-external-link-alt"></i> <span>Kunjungi Portal Lamaran</span>
                    </a>
                @endif
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fa fa-shield-halved me-2 text-success"></i>Verifikasi Rekrutmen</h6>
                    <p class="text-muted small mb-0">Semua lowongan yang dipublikasikan pada portal resmi kampus disarankan melalui proses verifikasi oleh pengelola alumni untuk menghindari penipuan rekrutmen kerja.</p>
                </div>
            </div>
        </div>
    </div>
</div>
