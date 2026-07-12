<?php

use App\Models\Alumni\EmployerPartner;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $partnerId;
    public array $form = [];
    public $logo;
    public ?string $existingLogo = null;

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.employer-partners.index');
    }

    public function mount($id): void
    {
        $partner = EmployerPartner::findOrFail($id);
        $this->partnerId = (int) $id;
        $this->existingLogo = $partner->logo_path;

        $this->form = [
            'name' => $partner->name,
            'industry' => $partner->industry,
            'website' => $partner->website ?? '',
            'contact_person' => $partner->contact_person ?? '',
            'contact_email' => $partner->contact_email ?? '',
            'contact_phone' => $partner->contact_phone ?? '',
            'address' => $partner->address ?? '',
            'city' => $partner->city ?? '',
            'province' => $partner->province ?? '',
            'description' => $partner->description ?? '',
            'is_active' => (bool) $partner->is_active,
        ];
    }

    public function updateEmployerPartner(): void
    {
        $validated = $this->validate([
            'form.name' => 'required|string|max:255',
            'form.industry' => 'required|string|max:255',
            'form.website' => 'nullable|url|max:500',
            'form.contact_person' => 'nullable|string|max:255',
            'form.contact_email' => 'nullable|email|max:255',
            'form.contact_phone' => 'nullable|string|max:30',
            'form.address' => 'nullable|string',
            'form.city' => 'nullable|string|max:255',
            'form.province' => 'nullable|string|max:255',
            'form.description' => 'nullable|string',
            'form.is_active' => 'boolean',
            'logo' => 'nullable|image|max:2048',
        ]);

        $partner = EmployerPartner::findOrFail($this->partnerId);
        $logoPath = $this->existingLogo;

        if ($this->logo) {
            $logoPath = $this->logo->store('employer-partners/logos', 'public');
        }

        $partner->update(array_merge(
            collect($validated['form'])->filter(fn ($v) => $v !== '')->toArray(),
            [
                'logo_path' => $logoPath,
                'updated_by' => auth()->id(),
            ]
        ));

        session()->flash('success', 'Mitra perusahaan berhasil diperbarui.');
        $this->redirectRoute('admin.alumni.employer-partners.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Edit Mitra Perusahaan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Edit Mitra: {{ $form['name'] ?? '' }}"
        description="Perbarui identitas perusahaan, sektor industri, kontak person, dan status kerjasama kemitraan karir."
        icon="building-circle-exclamation"
    >
        <a href="{{ route('admin.alumni.employer-partners.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-handshake fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Perbaruan Data Mitra</h4>
                            <div class="text-muted small">Kelola informasi perusahaan dan personil kontak untuk koordinasi rekrutmen.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-building me-2 text-primary"></i>Profil Perusahaan</h6>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="name" class="form-label fw-semibold">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" id="name" class="form-control rounded-3" wire:model.defer="form.name">
                            @error('form.name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="industry" class="form-label fw-semibold">Sektor Industri <span class="text-danger">*</span></label>
                            <input type="text" id="industry" class="form-control rounded-3" wire:model.defer="form.industry">
                            @error('form.industry') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="website" class="form-label fw-semibold">Website Resmi</label>
                            <input type="url" id="website" class="form-control rounded-3" wire:model.defer="form.website">
                            @error('form.website') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="logo" class="form-label fw-semibold">Logo Perusahaan (Maks. 2MB)</label>
                            @if ($existingLogo)
                                <div class="mb-2 d-flex align-items-center gap-2">
                                    <img src="{{ Storage::url($existingLogo) }}" alt="Logo" class="rounded object-fit-contain bg-light p-1 shadow-sm border" style="width: 48px; height: 36px;">
                                    <small class="text-success fw-medium"><i class="fa fa-check-circle me-1"></i>Logo saat ini tersimpan</small>
                                </div>
                            @endif
                            <input type="file" id="logo" class="form-control rounded-3" wire:model="logo" accept="image/*">
                            <div wire:loading wire:target="logo" class="text-muted small mt-1"><i class="fa fa-spinner fa-spin me-1"></i> Mengunggah logo...</div>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Deskripsi Singkat Perusahaan</label>
                            <textarea id="description" class="form-control rounded-3" rows="3" wire:model.defer="form.description"></textarea>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-address-book me-2 text-info"></i>Personil Kontak & Domisili</h6>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="contact_person" class="form-label fw-semibold">Nama Kontak Person</label>
                            <input type="text" id="contact_person" class="form-control rounded-3" wire:model.defer="form.contact_person">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="contact_email" class="form-label fw-semibold">Email Kontak</label>
                            <input type="email" id="contact_email" class="form-control rounded-3" wire:model.defer="form.contact_email">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="contact_phone" class="form-label fw-semibold">No. Telepon Kontak</label>
                            <input type="text" id="contact_phone" class="form-control rounded-3" wire:model.defer="form.contact_phone">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="city" class="form-label fw-semibold">Kota</label>
                            <input type="text" id="city" class="form-control rounded-3" wire:model.defer="form.city">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="province" class="form-label fw-semibold">Provinsi</label>
                            <input type="text" id="province" class="form-control rounded-3" wire:model.defer="form.province">
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label fw-semibold">Alamat Kantor</label>
                            <textarea id="address" class="form-control rounded-3" rows="2" wire:model.defer="form.address"></textarea>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="form.is_active">
                                    <label class="form-check-label fw-semibold" for="is_active">Mitra Aktif & Siap Menerima Lowongan</label>
                                </div>
                                <div class="text-muted small mt-1">Mitra yang aktif dapat dikaitkan dengan lowongan baru dan ditampilkan pada direktori karir.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                            <i class="fa fa-times me-2"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" wire:click="updateEmployerPartner">
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
                            <div class="text-muted small">Pemutakhiran data mitra membantu validasi lowongan kerja baru.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2.5">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Periksa status keaktifan jika perusahaan sementara tidak membuka lowongan.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan logo akan langsung diperbarui pada seluruh lowongan kerja yang terkait.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Kerja Sama</div>
                    <div class="fw-bold fs-5 {{ $form['is_active'] ? 'text-success' : 'text-danger' }} mb-2">{{ $form['is_active'] ? 'Aktif' : 'Nonaktif' }}</div>
                    <p class="text-muted small mb-0">Atur status keaktifan untuk menentukan apakah mitra ini muncul saat membuat lowongan karir.</p>
                </div>
            </div>
        </div>
    </div>
</div>
