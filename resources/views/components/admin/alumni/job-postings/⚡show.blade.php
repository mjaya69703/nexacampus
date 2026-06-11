<?php

use App\Enums\JobType;
use App\Models\Alumni\JobPosting;
use Livewire\Component;

new class extends Component
{
    public JobPosting $posting;

    public function mount($id): void
    {
        $this->posting = JobPosting::query()
            ->with(['employerPartner'])
            ->findOrFail($id);
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.alumni.job-postings.index');
    }

    public function render()
    {
        $jobTypes = JobType::options();

        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Detail Lowongan Kerja',
            'jobTypes' => $jobTypes,
        ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Lowongan Kerja</h5>
                <div>
                    @activecan('job-posting.update')
                    <a href="{{ route('admin.alumni.job-postings.edit', ['id' => $posting->id]) }}" class="btn btn-warning">
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
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Judul Lowongan</label>
                        <div class="h6 mb-0">{{ $posting->title }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Perusahaan</label>
                        <div class="h6 mb-0">{{ $posting->company_name }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Mitra</label>
                        <div class="h6 mb-0">{{ $posting->employerPartner?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Industri</label>
                        <div class="h6 mb-0">{{ $posting->industry ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Tipe</label>
                        <div class="h6 mb-0">{{ $jobTypes[$posting->job_type] ?? $posting->job_type ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Lokasi</label>
                        <div class="h6 mb-0">{{ $posting->location ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Range Gaji</label>
                        <div class="h6 mb-0">{{ $posting->salary_range ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            @if ($posting->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Nonaktif</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Tanggal Posting</label>
                        <div class="h6 mb-0">{{ $posting->posted_date?->format('d M Y') ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Deadline</label>
                        <div class="h6 mb-0">{{ $posting->deadline_date?->format('d M Y') ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Email Kontak</label>
                        <div class="h6 mb-0">{{ $posting->contact_email ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">URL Lamaran</label>
                        <div class="h6 mb-0">
                            @if ($posting->apply_url)
                                <a href="{{ $posting->apply_url }}" target="_blank">{{ $posting->apply_url }}</a>
                            @else - @endif
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Deskripsi</label>
                        <div class="p-2 bg-light rounded">{!! nl2br(e($posting->description)) !!}</div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Persyaratan</label>
                        <div class="p-2 bg-light rounded">{!! nl2br(e($posting->requirements ?? '-')) !!}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
