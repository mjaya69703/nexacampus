<?php

use App\Enums\JobType;
use App\Models\Alumni\EmployerPartner;
use App\Models\Alumni\JobPosting;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public $partners = [];
    public array $jobTypes = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.job-postings.index');
    }

    public function mount(): void
    {
        $this->partners = EmployerPartner::where('is_active', true)->orderBy('name')->get();
        $this->jobTypes = JobType::options();
        $this->form = [
            'employer_partner_id' => '',
            'title' => '',
            'company_name' => '',
            'industry' => '',
            'description' => '',
            'requirements' => '',
            'location' => '',
            'job_type' => '',
            'salary_range' => '',
            'apply_url' => '',
            'contact_email' => '',
            'posted_date' => now()->format('Y-m-d'),
            'deadline_date' => '',
            'is_active' => true,
        ];
    }

    public function createJobPosting(): void
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

        JobPosting::create(array_merge(
            collect($validated['form'])->filter(fn ($v) => $v !== '')->toArray(),
            [
                'employer_partner_id' => $validated['form']['employer_partner_id'] ?: null,
                'created_by' => auth()->id(),
            ]
        ));

        session()->flash('success', 'Lowongan kerja berhasil ditambahkan.');
        $this->redirectRoute('admin.alumni.job-postings.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Tambah Lowongan Kerja',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header"><h3 class="card-title">Tambah Lowongan Kerja</h3></div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="title">Judul Lowongan <span class="text-danger">*</span></label>
                <input type="text" id="title" class="form-control" wire:model.defer="form.title">
                @error('form.title') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="company_name">Nama Perusahaan <span class="text-danger">*</span></label>
                <input type="text" id="company_name" class="form-control" wire:model.defer="form.company_name">
                @error('form.company_name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="employer_partner_id">Mitra Perusahaan</label>
                <select id="employer_partner_id" class="form-control" wire:model.defer="form.employer_partner_id">
                    <option value="">-- Pilih Mitra (opsional) --</option>
                    @foreach ($partners as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="industry">Industri</label>
                <input type="text" id="industry" class="form-control" wire:model.defer="form.industry">
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="job_type">Tipe Pekerjaan</label>
                <select id="job_type" class="form-control" wire:model.defer="form.job_type">
                    <option value="">-- Pilih Tipe --</option>
                    @foreach ($jobTypes as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="location">Lokasi</label>
                <input type="text" id="location" class="form-control" wire:model.defer="form.location">
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="salary_range">Range Gaji</label>
                <input type="text" id="salary_range" class="form-control" wire:model.defer="form.salary_range" placeholder="Contoh: 5-8 juta">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="posted_date">Tanggal Posting <span class="text-danger">*</span></label>
                <input type="date" id="posted_date" class="form-control" wire:model.defer="form.posted_date">
                @error('form.posted_date') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="deadline_date">Deadline <span class="text-danger">*</span></label>
                <input type="date" id="deadline_date" class="form-control" wire:model.defer="form.deadline_date">
                @error('form.deadline_date') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="apply_url">URL Lamaran</label>
                <input type="url" id="apply_url" class="form-control" wire:model.defer="form.apply_url">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="contact_email">Email Kontak</label>
                <input type="email" id="contact_email" class="form-control" wire:model.defer="form.contact_email">
            </div>
            <div class="form-group col-12 mt-2">
                <label for="description">Deskripsi <span class="text-danger">*</span></label>
                <textarea id="description" class="form-control" rows="4" wire:model.defer="form.description"></textarea>
                @error('form.description') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <label for="requirements">Persyaratan</label>
                <textarea id="requirements" class="form-control" rows="4" wire:model.defer="form.requirements"></textarea>
            </div>
            <div class="form-group col-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="form.is_active">
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
            </div>
            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="createJobPosting">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
