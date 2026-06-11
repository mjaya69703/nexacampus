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
            'menus' => 'Alumni',
            'pages' => 'Edit Mitra Perusahaan',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Edit Mitra: {{ $form['name'] ?? '' }}</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="name">Nama Perusahaan <span class="text-danger">*</span></label>
                <input type="text" id="name" class="form-control" wire:model.defer="form.name">
                @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="industry">Industri <span class="text-danger">*</span></label>
                <input type="text" id="industry" class="form-control" wire:model.defer="form.industry">
                @error('form.industry') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="website">Website</label>
                <input type="url" id="website" class="form-control" wire:model.defer="form.website">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="contact_person">Kontak Person</label>
                <input type="text" id="contact_person" class="form-control" wire:model.defer="form.contact_person">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="contact_email">Email Kontak</label>
                <input type="email" id="contact_email" class="form-control" wire:model.defer="form.contact_email">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="contact_phone">No. Telepon</label>
                <input type="text" id="contact_phone" class="form-control" wire:model.defer="form.contact_phone">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="city">Kota</label>
                <input type="text" id="city" class="form-control" wire:model.defer="form.city">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="province">Provinsi</label>
                <input type="text" id="province" class="form-control" wire:model.defer="form.province">
            </div>
            <div class="form-group col-12 mt-2">
                <label for="address">Alamat</label>
                <textarea id="address" class="form-control" rows="2" wire:model.defer="form.address"></textarea>
            </div>
            <div class="form-group col-12 mt-2">
                <label for="description">Deskripsi</label>
                <textarea id="description" class="form-control" rows="3" wire:model.defer="form.description"></textarea>
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="logo">Logo</label>
                @if ($existingLogo)
                    <div class="mb-2"><small class="text-muted">Logo saat ini tersedia</small></div>
                @endif
                <input type="file" id="logo" class="form-control" wire:model="logo" accept="image/*">
            </div>
            <div class="form-group col-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="form.is_active">
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="updateEmployerPartner">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
