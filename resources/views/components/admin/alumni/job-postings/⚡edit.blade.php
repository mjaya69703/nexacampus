<?php

use App\Enums\JobType;
use App\Models\Alumni\EmployerPartner;
use App\Models\Alumni\JobPosting;
use Livewire\Component;

new class extends Component
{
    public int $postingId;
    public array $form = [];
    public $partners = [];
    public array $jobTypes = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.job-postings.index');
    }

    public function mount($id): void
    {
        $posting = JobPosting::findOrFail($id);
        $this->postingId = (int) $id;
        $this->partners = EmployerPartner::where('is_active', true)->orderBy('name')->get();
        $this->jobTypes = JobType::options();

        $this->form = [
            'employer_partner_id' => $posting->employer_partner_id ?? '',
            'title' => $posting->title,
            'company_name' => $posting->company_name,
            'industry' => $posting->industry ?? '',
            'description' => $posting->description,
            'requirements' => $posting->requirements ?? '',
            'location' => $posting->location ?? '',
            'job_type' => $posting->job_type ?? '',
            'salary_range' => $posting->salary_range ?? '',
            'apply_url' => $posting->apply_url ?? '',
            'contact_email' => $posting->contact_email ?? '',
            'posted_date' => $posting->posted_date?->format('Y-m-d') ?? '',
            'deadline_date' => $posting->deadline_date?->format('Y-m-d') ?? '',
            'is_active' => (bool) $posting->is_active,
        ];
    }

    public function updateJobPosting(): void
    {
        $validated = $this->validate([
            'form.employer_partner_id' => 'nullable|exists:employer_partners,id',
            'form.title' => 'required|string|max:255',
            'form.company_name' => 'required|string|max:255',
            'form.industry' => 'nullable|string|max:255',
            'form.description' => 'required|string',
            'form.requirements' => 'nullable|string',
            'form.location' => 'nullable|string|max:255',
            'form.job_type' => 'nullable|string',
            'form.salary_range' => 'nullable|string|max:255',
            'form.apply_url' => 'nullable|url|max:500',
            'form.contact_email' => 'nullable|email|max:255',
            'form.posted_date' => 'required|date',
            'form.deadline_date' => 'required|date|after_or_equal:form.posted_date',
            'form.is_active' => 'boolean',
        ]);

        $posting = JobPosting::findOrFail($this->postingId);

        $posting->update(array_merge(
            collect($validated['form'])->filter(fn ($v) => $v !== '')->toArray(),
            [
                'employer_partner_id' => $validated['form']['employer_partner_id'] ?: null,
                'updated_by' => auth()->id(),
            ]
        ));

        session()->flash('success', 'Lowongan kerja berhasil diperbarui.');
        $this->redirectRoute('admin.alumni.job-postings.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Edit Lowongan Kerja',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Edit Lowongan: {{ $form['title'] ?? '' }}"
        description="Perbarui informasi posisi karir, kualifikasi persyaratan, serta jadwal publikasi atau deadline lamaran."
        icon="briefcase"
    >
        <a href="{{ route('admin.alumni.job-postings.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-edit fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Perbaruan Lowongan Kerja</h4>
                            <div class="text-muted small">Pastikan tautan pendaftaran dan email kontak tujuan lamaran masih aktif.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-info-circle me-2 text-primary"></i>Identitas Posisi & Perusahaan</h6>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="title" class="form-label fw-semibold">Judul Lowongan <span class="text-danger">*</span></label>
                            <input type="text" id="title" class="form-control rounded-3" wire:model.defer="form.title">
                            @error('form.title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="company_name" class="form-label fw-semibold">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" id="company_name" class="form-control rounded-3" wire:model.defer="form.company_name">
                            @error('form.company_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="employer_partner_id" class="form-label fw-semibold">Mitra Terdaftar (Opsional)</label>
                            <select id="employer_partner_id" class="form-control rounded-3" wire:model.defer="form.employer_partner_id">
                                <option value="">-- Pilih dari Mitra Perusahaan --</option>
                                @foreach ($partners as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="industry" class="form-label fw-semibold">Sektor Industri</label>
                            <input type="text" id="industry" class="form-control rounded-3" wire:model.defer="form.industry">
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-map-marker-alt me-2 text-info"></i>Penempatan & Kompensasi</h6>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="job_type" class="form-label fw-semibold">Tipe Pekerjaan</label>
                            <select id="job_type" class="form-control rounded-3" wire:model.defer="form.job_type">
                                <option value="">-- Pilih Tipe --</option>
                                @foreach ($jobTypes as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="location" class="form-label fw-semibold">Lokasi Kerja</label>
                            <input type="text" id="location" class="form-control rounded-3" wire:model.defer="form.location">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="salary_range" class="form-label fw-semibold">Rentang Gaji</label>
                            <input type="text" id="salary_range" class="form-control rounded-3" wire:model.defer="form.salary_range">
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-calendar-alt me-2 text-success"></i>Periode & Cara Melamar</h6>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="posted_date" class="form-label fw-semibold">Tanggal Posting <span class="text-danger">*</span></label>
                            <input type="date" id="posted_date" class="form-control rounded-3" wire:model.defer="form.posted_date">
                            @error('form.posted_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="deadline_date" class="form-label fw-semibold">Batas Akhir (Deadline) <span class="text-danger">*</span></label>
                            <input type="date" id="deadline_date" class="form-control rounded-3" wire:model.defer="form.deadline_date">
                            @error('form.deadline_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="apply_url" class="form-label fw-semibold">Tautan Pendaftaran (URL)</label>
                            <input type="url" id="apply_url" class="form-control rounded-3" wire:model.defer="form.apply_url">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="contact_email" class="form-label fw-semibold">Email Pengiriman CV</label>
                            <input type="email" id="contact_email" class="form-control rounded-3" wire:model.defer="form.contact_email">
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-align-left me-2 text-warning"></i>Deskripsi & Persyaratan</h6>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Deskripsi Pekerjaan <span class="text-danger">*</span></label>
                            <textarea id="description" class="form-control rounded-3" rows="4" wire:model.defer="form.description"></textarea>
                            @error('form.description') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="requirements" class="form-label fw-semibold">Kualifikasi & Persyaratan</label>
                            <textarea id="requirements" class="form-control rounded-3" rows="4" wire:model.defer="form.requirements"></textarea>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="form.is_active">
                                    <label class="form-check-label fw-semibold" for="is_active">Langsung Aktif & Ditampilkan di Portal Karir</label>
                                </div>
                                <div class="text-muted small mt-1">Jika nonaktif, lowongan akan disembunyikan sementara dari penelusuran publik.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                            <i class="fa fa-times me-2"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" wire:click="updateJobPosting">
                            <i class="fa fa-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-history fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Riwayat Data</h5>
                            <div class="text-muted small">Perbarui status jika lowongan sudah terpenuhi atau kedaluwarsa.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2.5">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Periksa batas waktu lamaran agar tidak melewati tanggal hari ini bila masih dibuka.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan URL pendaftaran dapat diakses tanpa kendala izin oleh pelamar.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Publikasi</div>
                    <div class="fw-bold fs-5 {{ $form['is_active'] ? 'text-success' : 'text-danger' }} mb-2">{{ $form['is_active'] ? 'Aktif' : 'Nonaktif' }}</div>
                    <p class="text-muted small mb-0">Atur status keaktifan untuk menentukan keterlihatan lowongan di portal alumni.</p>
                </div>
            </div>
        </div>
    </div>
</div>
