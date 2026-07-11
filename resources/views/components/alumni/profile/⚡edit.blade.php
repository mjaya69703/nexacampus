<?php

use App\Enums\EmploymentStatus;
use App\Models\Alumni\AlumniProfile;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'phone' => '',
        'address' => '',
        'current_city' => '',
        'current_province' => '',
        'employment_status' => '',
        'employer_name' => '',
        'job_title' => '',
        'job_industry' => '',
        'linkedin_url' => '',
    ];

    public function mount(): void
    {
        $user = auth()->user();
        $profile = AlumniProfile::query()->where('user_id', $user->id)->first();
        abort_unless($profile, 404);

        $this->form = [
            'phone' => $profile->phone ?? '',
            'address' => $profile->address ?? '',
            'current_city' => $profile->current_city ?? '',
            'current_province' => $profile->current_province ?? '',
            'employment_status' => $profile->employment_status ?? 'unemployed',
            'employer_name' => $profile->employer_name ?? '',
            'job_title' => $profile->job_title ?? '',
            'job_industry' => $profile->job_industry ?? '',
            'linkedin_url' => $profile->linkedin_url ?? '',
        ];
    }

    public function save(): void
    {
        $user = auth()->user();
        $profile = AlumniProfile::query()->where('user_id', $user->id)->first();
        abort_unless($profile, 422, 'Profil alumni belum tersedia.');

        $validated = $this->validate([
            'form.phone' => ['nullable', 'string', 'max:50'],
            'form.address' => ['nullable', 'string', 'max:500'],
            'form.current_city' => ['nullable', 'string', 'max:100'],
            'form.current_province' => ['nullable', 'string', 'max:100'],
            'form.employment_status' => ['required', 'string'],
            'form.employer_name' => ['nullable', 'string', 'max:255'],
            'form.job_title' => ['nullable', 'string', 'max:255'],
            'form.job_industry' => ['nullable', 'string', 'max:100'],
            'form.linkedin_url' => ['nullable', 'url', 'max:500'],
        ])['form'];

        $profile->update($validated);

        session()->flash('success', 'Profil berhasil diperbarui.');
        $this->redirectRoute('alumni.profile.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Profil',
            'pages' => 'Edit Profil Alumni',
        ]);
    }
};
?>

<div>
    <x-alert />

    {{-- Hero --}}
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 16px;">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-pen-to-square"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Portal Alumni</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Edit Profil</h1>
                        <div style="opacity: 0.9;">Perbarui informasi kontak, karir, dan data lainnya.</div>
                    </div>
                </div>
                <a href="{{ route('alumni.profile.index') }}" class="btn btn-light fw-semibold">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="save">
        <div class="row g-4">
            {{-- Contact Info --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0"><i class="fas fa-address-card me-2 text-primary"></i>Informasi Kontak</h3>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">No. Telepon</label>
                            <input type="text" wire:model.defer="form.phone" class="form-control" placeholder="08xxxxxxxxxx">
                            @error('form.phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Alamat</label>
                            <textarea wire:model.defer="form.address" class="form-control" rows="3" placeholder="Alamat lengkap"></textarea>
                            @error('form.address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Kota</label>
                                <input type="text" wire:model.defer="form.current_city" class="form-control" placeholder="Kota saat ini">
                                @error('form.current_city') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Provinsi</label>
                                <input type="text" wire:model.defer="form.current_province" class="form-control" placeholder="Provinsi">
                                @error('form.current_province') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Career Info --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0"><i class="fas fa-briefcase me-2 text-primary"></i>Informasi Karir</h3>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Status Pekerjaan <span class="text-danger">*</span></label>
                            <select wire:model.live="form.employment_status" class="form-select">
                                @foreach (EmploymentStatus::cases() as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('form.employment_status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        @if (in_array($form['employment_status'], ['working', 'entrepreneur']))
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nama Perusahaan</label>
                                <input type="text" wire:model.defer="form.employer_name" class="form-control" placeholder="Nama perusahaan/instansi">
                                @error('form.employer_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Jabatan</label>
                                <input type="text" wire:model.defer="form.job_title" class="form-control" placeholder="Jabatan/posisi">
                                @error('form.job_title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Industri</label>
                                <input type="text" wire:model.defer="form.job_industry" class="form-control" placeholder="Contoh: Teknologi, Keuangan, Pendidikan">
                                @error('form.job_industry') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label fw-semibold">LinkedIn URL</label>
                            <input type="url" wire:model.defer="form.linkedin_url" class="form-control" placeholder="https://linkedin.com/in/username">
                            @error('form.linkedin_url') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('alumni.profile.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary fw-semibold">
                <i class="fas fa-save me-1"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>
